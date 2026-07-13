<?php

namespace App\Tests\Controller;

use App\Entity\Ban;
use App\Entity\FixedHolidayError;
use App\Entity\FixedHolidaySuggestion;
use App\Entity\ReportState;
use App\Tests\Fixture\BanFixture;
use App\Tests\Fixture\CountryFixture;
use App\Tests\Fixture\FixedHolidayErrorFixture;
use App\Tests\Fixture\FixedHolidaySuggestionFixture;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminUserControllerTest extends WebTestCase
{
	private const array CREDENTIALS = ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin'];
	private const string REPORTER = 'user-id';

	private KernelBrowser $client;
	private EntityManagerInterface $em;
	private AbstractDatabaseTool $databaseTool;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();
		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();
		$this->databaseTool->loadFixtures([
			CountryFixture::class,
			FixedHolidaySuggestionFixture::class,
			FixedHolidayErrorFixture::class,
			BanFixture::class,
		]);
		$this->em = static::getContainer()->get(EntityManagerInterface::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	public function testStatsPageListsReporters(): void
	{
		$crawler = $this->client->request('GET', '/admin/users', [], [], self::CREDENTIALS);

		$this->assertResponseIsSuccessful();
		$this->assertSame(1, $crawler->filter(sprintf('tr[data-user-row="%s"]', self::REPORTER))->count());
	}

	public function testBannedPageListsBans(): void
	{
		$crawler = $this->client->request('GET', '/admin/users/banned', [], [], self::CREDENTIALS);

		$this->assertResponseIsSuccessful();
		$this->assertSame(1, $crawler->filter('tr[data-user-row="user-id-banned"]')->count());
	}

	public function testReportsPageMarksBannedReporter(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => self::REPORTER, 'reason' => 'Spam']);
		$this->assertResponseIsSuccessful();

		$crawler = $this->client->request('GET', '/admin/reports/fixed-suggestions', [], [], self::CREDENTIALS);

		$this->assertResponseIsSuccessful();
		$row = $crawler->filter('tr.report-row')->first();
		$this->assertSame('1', $row->attr('data-report-user-banned'));
		$this->assertSame('Spam', $row->attr('data-report-user-ban-reason'));
	}

	public function testReportCounts(): void
	{
		$this->client->request('GET', '/admin/api/users/report-counts', ['user_id' => self::REPORTER], [], self::CREDENTIALS);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(2, $payload['total']);
		$this->assertSame(2, $payload['pending']);
	}

	public function testBanKeepsReportsByDefault(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => self::REPORTER, 'reason' => 'Spam']);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(0, $payload['deleted_reports']);

		$ban = $this->em->getRepository(Ban::class)->findOneBy(['userId' => self::REPORTER]);
		$this->assertNotNull($ban);
		$this->assertSame('Spam', $ban->reason);
		$this->assertSame(1, $this->em->getRepository(FixedHolidaySuggestion::class)->count(['userId' => self::REPORTER]));
	}

	public function testBanDeletesAllReports(): void
	{
		$this->post('/admin/api/users/ban', [
			'user_id' => self::REPORTER,
			'reason' => 'Spam',
			'delete_reports' => 'all',
		]);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(2, $payload['deleted_reports']);
		$this->assertSame(0, $this->em->getRepository(FixedHolidaySuggestion::class)->count(['userId' => self::REPORTER]));
		$this->assertSame(0, $this->em->getRepository(FixedHolidayError::class)->count(['userId' => self::REPORTER]));
	}

	public function testBanRequiresReason(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => self::REPORTER, 'reason' => '  ']);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('reason');
		$this->assertNull($this->em->getRepository(Ban::class)->findOneBy(['userId' => self::REPORTER]));
	}

	public function testBanRequiresUserId(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => ' ', 'reason' => 'Spam']);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('user_id');
	}

	public function testBanRejectsTooLongReason(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => self::REPORTER, 'reason' => str_repeat('a', 2048)]);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('reason');
		$this->assertNull($this->em->getRepository(Ban::class)->findOneBy(['userId' => self::REPORTER]));
	}

	public function testBanAcceptsReasonAtMaximumLength(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => self::REPORTER, 'reason' => str_repeat('a', 2047)]);

		$this->assertResponseIsSuccessful();
		$this->assertNotNull($this->em->getRepository(Ban::class)->findOneBy(['userId' => self::REPORTER]));
	}

	public function testBanRejectsUnknownDeleteReportsScope(): void
	{
		$this->post('/admin/api/users/ban', [
			'user_id' => self::REPORTER,
			'reason' => 'Spam',
			'delete_reports' => 'everything',
		]);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('delete_reports');
		$this->assertNull($this->em->getRepository(Ban::class)->findOneBy(['userId' => self::REPORTER]));
	}

	public function testBanDeletesPendingReportsOnly(): void
	{
		$this->moderate($this->em->getRepository(FixedHolidaySuggestion::class)
			->findOneBy(['userId' => self::REPORTER]));

		$this->post('/admin/api/users/ban', [
			'user_id' => self::REPORTER,
			'reason' => 'Spam',
			'delete_reports' => 'pending',
		]);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(1, $payload['deleted_reports']);
		$this->assertSame(1, $this->em->getRepository(FixedHolidaySuggestion::class)->count(['userId' => self::REPORTER]));
		$this->assertSame(0, $this->em->getRepository(FixedHolidayError::class)->count(['userId' => self::REPORTER]));
	}

	public function testBanRejectsMalformedJsonBody(): void
	{
		$this->client->request('POST', '/admin/api/users/ban', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], 'not json');

		$this->assertResponseStatusCodeSame(400);
	}

	public function testBanRejectsInvalidCsrfToken(): void
	{
		$this->client->request('GET', '/admin/users', [], [], self::CREDENTIALS);
		$this->client->request('POST', '/admin/api/users/ban', [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode(['user_id' => self::REPORTER, 'reason' => 'Spam', '_token' => 'nope']));

		$this->assertResponseStatusCodeSame(400);
		$this->assertNull($this->em->getRepository(Ban::class)->findOneBy(['userId' => self::REPORTER]));
	}

	public function testBanOfAlreadyBannedUserOverwritesReason(): void
	{
		$this->post('/admin/api/users/ban', ['user_id' => 'user-id-banned', 'reason' => 'New reason']);

		$this->assertResponseIsSuccessful();
		$this->assertSame(1, $this->em->getRepository(Ban::class)->count(['userId' => 'user-id-banned']));
		$this->assertSame('New reason', $this->em->getRepository(Ban::class)
			->findOneBy(['userId' => 'user-id-banned'])->reason);
	}

	public function testUnban(): void
	{
		$this->post('/admin/api/users/unban', ['user_id' => 'user-id-banned']);

		$this->assertResponseIsSuccessful();
		$this->assertNull($this->em->getRepository(Ban::class)->findOneBy(['userId' => 'user-id-banned']));
	}

	public function testUnbanOfNotBannedUserIsNotFound(): void
	{
		$this->post('/admin/api/users/unban', ['user_id' => self::REPORTER]);

		$this->assertResponseStatusCodeSame(404);
	}

	public function testDeletePendingReportsOnly(): void
	{
		$this->post('/admin/api/users/reports/delete', ['user_id' => self::REPORTER, 'scope' => 'pending']);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(2, $payload['deleted_reports']);
		$this->assertSame(0, $this->em->getRepository(FixedHolidaySuggestion::class)->count(['userId' => self::REPORTER]));
	}

	public function testDeleteReportsRejectsUnknownScope(): void
	{
		$this->post('/admin/api/users/reports/delete', ['user_id' => self::REPORTER, 'scope' => 'everything']);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('scope');
		$this->assertSame(1, $this->em->getRepository(FixedHolidaySuggestion::class)->count(['userId' => self::REPORTER]));
	}

	public function testDeleteAllReportsOfUser(): void
	{
		$this->post('/admin/api/users/reports/delete', ['user_id' => self::REPORTER, 'scope' => 'all']);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(2, $payload['deleted_reports']);
		$this->assertSame('all', $payload['scope']);
		$this->assertSame(0, $this->em->getRepository(FixedHolidayError::class)->count(['userId' => self::REPORTER]));
	}

	public function testDeleteReportsRequiresUserId(): void
	{
		$this->post('/admin/api/users/reports/delete', ['user_id' => '', 'scope' => 'all']);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('user_id');
	}

	public function testUnbanRequiresUserId(): void
	{
		$this->post('/admin/api/users/unban', ['user_id' => '']);

		$this->assertResponseStatusCodeSame(400);
		$this->assertJsonError('user_id');
	}

	public function testReportCountsRequiresUserId(): void
	{
		$this->client->request('GET', '/admin/api/users/report-counts', [], [], self::CREDENTIALS);

		$this->assertResponseStatusCodeSame(400);
	}

	public function testReportCountsOfUserWithoutReports(): void
	{
		$this->client->request('GET', '/admin/api/users/report-counts', ['user_id' => 'user-id-banned'], [], self::CREDENTIALS);

		$this->assertResponseIsSuccessful();
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertSame(0, $payload['total']);
		$this->assertSame(0, $payload['pending']);
	}

	public function testSidebarShowsBannedCount(): void
	{
		$crawler = $this->client->request('GET', '/admin/users', [], [], self::CREDENTIALS);

		$this->assertSame('1', trim($crawler->filter('a[href="/admin/users/banned"] .sidebar-badge')->text()));
	}

	public function testUsersPagesRequireAuthentication(): void
	{
		$this->client->request('GET', '/admin/users');

		$this->assertResponseStatusCodeSame(401);
	}

	private function assertJsonError(string $field): void
	{
		$payload = json_decode($this->client->getResponse()->getContent(), true);
		$this->assertNotSame('', (string)($payload['error'] ?? ''));
		$this->assertSame($field, $payload['field'] ?? null);
	}

	/** Moves a report out of the REPORTED state, so "delete pending" has something to skip. */
	private function moderate(FixedHolidaySuggestion $suggestion): void
	{
		$this->em->createQueryBuilder()
			->update(FixedHolidaySuggestion::class, 'r')
			->set('r.reportState', ':state')
			->where('r.id = :id')
			->setParameter('state', ReportState::APPLIED)
			->setParameter('id', $suggestion->id)
			->getQuery()
			->execute();
	}

	/**
	 * The ban CSRF token is session-bound, so it has to be read from a page the same client rendered
	 * (the admin pages embed it on the shared ban modal) instead of pulled from the container.
	 *
	 * @param array<string, mixed> $body
	 */
	private function post(string $url, array $body): void
	{
		$crawler = $this->client->request('GET', '/admin/users', [], [], self::CREDENTIALS);
		$token = $crawler->filter('#banUserModal')->attr('data-csrf-token');

		$this->client->request('POST', $url, [], [], [
			...self::CREDENTIALS,
			'CONTENT_TYPE' => 'application/json',
		], json_encode([...$body, '_token' => $token]));
	}
}
