<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApiTest extends TestCase
{
    private static PDO $database;
    private static string $baseUrl;
    private static string $suffix;
    private static string $adminEmail;
    private static string $sellerEmail;
    private static string $secondSellerEmail;
    private static string $adminToken;
    private static string $sellerToken;
    private static string $secondSellerToken;
    private static int $sellerId;
    /** @var list<int> */
    private static array $productIds = [];
    /** @var list<int> */
    private static array $campaignIds = [];
    /** @var list<int> */
    private static array $saleIds = [];
    /** @var list<int> */
    private static array $userIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8080', '/');
        self::$suffix = bin2hex(random_bytes(6));
        self::$database = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('TEST_DB_HOST') ?: '127.0.0.1',
                getenv('TEST_DB_PORT') ?: '13306',
                getenv('TEST_DB_DATABASE') ?: 'vendeu_ganhou',
            ),
            getenv('TEST_DB_USERNAME') ?: 'app',
            getenv('TEST_DB_PASSWORD') ?: 'app_local_password',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );

        self::$adminEmail = self::email('admin');
        self::$sellerEmail = self::email('seller');
        self::$secondSellerEmail = self::email('seller-two');

        self::$userIds[] = self::createUser(self::$adminEmail, 'Test Admin', 'admin');
        self::$sellerId = self::createUser(self::$sellerEmail, 'Test Seller', 'seller');
        self::$userIds[] = self::$sellerId;
        self::$userIds[] = self::createUser(self::$secondSellerEmail, 'Test Seller Two', 'seller');

        self::$adminToken = self::login(self::$adminEmail, 'test-password');
        self::$sellerToken = self::login(self::$sellerEmail, 'test-password');
        self::$secondSellerToken = self::login(self::$secondSellerEmail, 'test-password');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$saleIds as $saleId) {
            self::$database->prepare('DELETE FROM wallet_entries WHERE sale_id = :sale_id')->execute(['sale_id' => $saleId]);
            self::$database->prepare('DELETE FROM sales WHERE id = :id')->execute(['id' => $saleId]);
        }

        foreach (self::$campaignIds as $campaignId) {
            self::$database->prepare('DELETE FROM campaigns WHERE id = :id')->execute(['id' => $campaignId]);
        }

        foreach (self::$productIds as $productId) {
            self::$database->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
        }

        foreach (self::$userIds as $userId) {
            self::$database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        }
    }

    public function testAuthenticationAndRoleAccess(): void
    {
        $correctLogin = self::request('POST', '/auth/login', null, [
            'email' => self::$adminEmail,
            'password' => 'test-password',
        ]);
        $incorrectLogin = self::request('POST', '/auth/login', null, [
            'email' => self::$adminEmail,
            'password' => 'wrong-password',
        ]);
        $invalidToken = self::request('GET', '/products', 'invalid-token');
        $sellerAdminRoute = self::request('GET', '/products', self::$sellerToken);
        $adminRoute = self::request('GET', '/products', self::$adminToken);

        $this->assertSame(200, $correctLogin['status']);
        $this->assertArrayHasKey('token', $correctLogin['body']);
        $this->assertSame(401, $incorrectLogin['status']);
        $this->assertSame(401, $invalidToken['status']);
        $this->assertSame(403, $sellerAdminRoute['status']);
        $this->assertSame(200, $adminRoute['status']);
    }

    public function testProductAndCampaignCreation(): void
    {
        $product = self::createProduct(10);
        $duplicateProduct = self::request('POST', '/products', self::$adminToken, [
            'name' => $product['name'],
            'sku' => $product['sku'],
            'points_per_unit' => 10,
            'active' => true,
        ]);
        $campaign = self::createCampaign(100);

        $this->assertSame(201, $product['response']['status']);
        $this->assertSame(409, $duplicateProduct['status']);
        $this->assertSame(201, $campaign['response']['status']);
        $this->assertSame(0, $campaign['body']['campaign']['budget_used']);
        $this->assertSame(100, $campaign['body']['campaign']['budget_remaining']);
    }

    public function testScoringBudgetAndIdempotency(): void
    {
        $product = self::createProduct(10);
        $campaign = self::createCampaign(100);
        $campaignId = $campaign['body']['campaign']['id'];
        $externalId = self::externalId('score');

        $invalidQuantity = self::request('POST', '/sales', self::$adminToken, [
            'external_id' => self::externalId('invalid'),
            'campaign_id' => $campaignId,
            'seller_id' => self::$sellerId,
            'product_id' => $product['id'],
            'quantity' => 0,
            'unit_value' => 10,
        ]);
        $sale = self::request('POST', '/sales', self::$adminToken, [
            'external_id' => $externalId,
            'campaign_id' => $campaignId,
            'seller_id' => self::$sellerId,
            'product_id' => $product['id'],
            'quantity' => 6,
            'unit_value' => 12.50,
        ]);
        $saleId = $sale['body']['sale']['id'];
        self::$saleIds[] = $saleId;
        $duplicateSale = self::request('POST', '/sales', self::$adminToken, [
            'external_id' => $externalId,
            'campaign_id' => $campaignId,
            'seller_id' => self::$sellerId,
            'product_id' => $product['id'],
            'quantity' => 6,
            'unit_value' => 12.50,
        ]);
        $overBudgetExternalId = self::externalId('over-budget');
        $insufficientSale = self::request('POST', '/sales', self::$adminToken, [
            'external_id' => $overBudgetExternalId,
            'campaign_id' => $campaignId,
            'seller_id' => self::$sellerId,
            'product_id' => $product['id'],
            'quantity' => 5,
            'unit_value' => 12.50,
        ]);

        $this->assertSame(422, $invalidQuantity['status']);
        $this->assertSame(201, $sale['status']);
        $this->assertSame(60, $sale['body']['sale']['points']);
        $this->assertSame(200, $duplicateSale['status']);
        $this->assertSame($saleId, $duplicateSale['body']['sale']['id']);
        $this->assertSame(422, $insufficientSale['status']);
        $this->assertSame(60, self::campaignBudget($campaignId));
        $this->assertSame(0, self::saleCountByExternalId($overBudgetExternalId));
        $this->assertSame(1, self::walletCount($saleId, 'credit'));
        $this->assertSame(60, self::walletPoints($saleId, 'credit'));
    }

    public function testCancellationAndWalletLedger(): void
    {
        $product = self::createProduct(10);
        $campaign = self::createCampaign(100);
        $campaignId = $campaign['body']['campaign']['id'];
        $externalId = self::externalId('cancel');
        $balanceBefore = self::sellerBalance(self::$sellerId);
        $sale = self::request('POST', '/sales', self::$adminToken, [
            'external_id' => $externalId,
            'campaign_id' => $campaignId,
            'seller_id' => self::$sellerId,
            'product_id' => $product['id'],
            'quantity' => 4,
            'unit_value' => 20,
        ]);
        $saleId = $sale['body']['sale']['id'];
        self::$saleIds[] = $saleId;
        $cancel = self::request('POST', '/sales/' . $externalId . '/cancel', self::$adminToken);
        $duplicateCancel = self::request('POST', '/sales/' . $externalId . '/cancel', self::$adminToken);
        $notFound = self::request('POST', '/sales/' . self::externalId('missing') . '/cancel', self::$adminToken);
        $wallet = self::request('GET', '/me/wallet', self::$sellerToken);

        $this->assertSame(201, $sale['status']);
        $this->assertSame(200, $cancel['status']);
        $this->assertSame('canceled', $cancel['body']['sale']['status']);
        $this->assertSame(40, $cancel['body']['sale']['points']);
        $this->assertSame(200, $duplicateCancel['status']);
        $this->assertSame(404, $notFound['status']);
        $this->assertSame(1, self::walletCount($saleId, 'credit'));
        $this->assertSame(1, self::walletCount($saleId, 'debit'));
        $this->assertSame(40, self::walletPoints($saleId, 'credit'));
        $this->assertSame(40, self::walletPoints($saleId, 'debit'));
        $this->assertSame(0, self::campaignBudget($campaignId));
        $this->assertSame($balanceBefore, self::sellerBalance(self::$sellerId));
        $this->assertSame(200, $wallet['status']);
        $this->assertSame($balanceBefore, $wallet['body']['balance']);
        $this->assertSame(self::sellerEntryCount(self::$sellerId), count($wallet['body']['entries']));
        $this->assertContains('credit', array_column($wallet['body']['entries'], 'type'));
        $this->assertContains('debit', array_column($wallet['body']['entries'], 'type'));
    }

    public function testSellerWalletOwnershipCannotBeOverridden(): void
    {
        $wallet = self::request('GET', '/me/wallet?seller_id=' . self::$sellerId, self::$secondSellerToken);

        $this->assertSame(200, $wallet['status']);
        $this->assertSame(0, $wallet['body']['balance']);
        $this->assertSame([], $wallet['body']['entries']);
        $this->assertFalse(array_key_exists('seller_id', $wallet['body']));
    }

    /** @return array{id: int, name: string, sku: string, response: array{status: int, body: array<string, mixed>}} */
    private static function createProduct(int $pointsPerUnit): array
    {
        $name = 'PHPUnit Product ' . self::$suffix . '-' . count(self::$productIds);
        $sku = 'PHPUNIT-' . self::$suffix . '-' . count(self::$productIds);
        $response = self::request('POST', '/products', self::$adminToken, [
            'name' => $name,
            'sku' => $sku,
            'points_per_unit' => $pointsPerUnit,
            'active' => true,
        ]);

        if ($response['status'] !== 201) {
            throw new RuntimeException('Could not create test product: ' . json_encode($response['body']));
        }

        $id = (int) $response['body']['product']['id'];
        self::$productIds[] = $id;

        return ['id' => $id, 'name' => $name, 'sku' => $sku, 'response' => $response];
    }

    /** @return array{body: array<string, mixed>, response: array{status: int, body: array<string, mixed>}} */
    private static function createCampaign(int $budgetTotal): array
    {
        $response = self::request('POST', '/campaigns', self::$adminToken, [
            'name' => 'PHPUnit Campaign ' . self::$suffix . '-' . count(self::$campaignIds),
            'budget_total' => $budgetTotal,
            'starts_at' => '2020-01-01 00:00:00',
            'ends_at' => '2035-12-31 23:59:59',
            'status' => 'active',
        ]);

        if ($response['status'] !== 201) {
            throw new RuntimeException('Could not create test campaign: ' . json_encode($response['body']));
        }

        self::$campaignIds[] = (int) $response['body']['campaign']['id'];

        return ['body' => $response['body'], 'response' => $response];
    }

    private static function createUser(string $email, string $name, string $role): int
    {
        $statement = self::$database->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)',
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return (int) self::$database->lastInsertId();
    }

    private static function login(string $email, string $password): string
    {
        $response = self::request('POST', '/auth/login', null, ['email' => $email, 'password' => $password]);

        if ($response['status'] !== 200 || !is_string($response['body']['token'] ?? null)) {
            throw new RuntimeException('Could not authenticate test user: ' . json_encode($response['body']));
        }

        return $response['body']['token'];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private static function request(string $method, string $path, ?string $token, ?array $payload = null): array
    {
        $handle = curl_init(self::$baseUrl . $path);

        if ($handle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $headers = ['Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($handle, $options);
        $rawBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($rawBody === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $body = json_decode($rawBody, true);

        return [
            'status' => $status,
            'body' => is_array($body) ? $body : [],
        ];
    }

    private static function campaignBudget(int $campaignId): int
    {
        $statement = self::$database->prepare('SELECT budget_used FROM campaigns WHERE id = :id');
        $statement->execute(['id' => $campaignId]);

        return (int) $statement->fetchColumn();
    }

    private static function sellerBalance(int $sellerId): int
    {
        $statement = self::$database->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN points ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = 'debit' THEN points ELSE 0 END), 0) FROM wallet_entries WHERE seller_id = :seller_id",
        );
        $statement->execute(['seller_id' => $sellerId]);

        return (int) $statement->fetchColumn();
    }

    private static function sellerEntryCount(int $sellerId): int
    {
        $statement = self::$database->prepare('SELECT COUNT(*) FROM wallet_entries WHERE seller_id = :seller_id');
        $statement->execute(['seller_id' => $sellerId]);

        return (int) $statement->fetchColumn();
    }

    private static function walletCount(int $saleId, string $type): int
    {
        $statement = self::$database->prepare('SELECT COUNT(*) FROM wallet_entries WHERE sale_id = :sale_id AND type = :type');
        $statement->execute(['sale_id' => $saleId, 'type' => $type]);

        return (int) $statement->fetchColumn();
    }

    private static function walletPoints(int $saleId, string $type): int
    {
        $statement = self::$database->prepare('SELECT points FROM wallet_entries WHERE sale_id = :sale_id AND type = :type');
        $statement->execute(['sale_id' => $saleId, 'type' => $type]);

        return (int) $statement->fetchColumn();
    }

    private static function saleCountByExternalId(string $externalId): int
    {
        $statement = self::$database->prepare('SELECT COUNT(*) FROM sales WHERE external_id = :external_id');
        $statement->execute(['external_id' => $externalId]);

        return (int) $statement->fetchColumn();
    }

    private static function email(string $role): string
    {
        return 'phpunit-' . $role . '-' . self::$suffix . '@vendeu.local';
    }

    private static function externalId(string $name): string
    {
        return 'PHPUNIT-' . $name . '-' . self::$suffix . '-' . bin2hex(random_bytes(3));
    }
}
