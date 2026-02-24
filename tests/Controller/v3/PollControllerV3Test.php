<?php

namespace App\Tests\Controller\v3;

use App\Entity\Poll;
use App\Entity\PollOption;
use App\Tests\Fixture\PollFixture;
use App\Tests\Trait\TestUtilTrait;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PollControllerV3Test extends WebTestCase
{
	use TestUtilTrait;

	private EntityManagerInterface $em;
	private AbstractDatabaseTool $databaseTool;
	private AbstractExecutor $fixtures;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = static::createClient();

		$this->databaseTool = static::getContainer()
			->get(DatabaseToolCollection::class)
			->get();

		$this->fixtures = $this->databaseTool->loadFixtures([
			PollFixture::class,
		]);

		$this->em = static::getContainer()
			->get(EntityManagerInterface::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->databaseTool);
	}

	/**
	 * @throws JsonException
	 */
	public function testListReturnsActivePolls(): void
	{
		/** @var Poll $activePoll */
		$activePoll = $this->getFixture('active-poll', Poll::class);
		/** @var Poll $pastPoll */
		$pastPoll = $this->getFixture('past-poll', Poll::class);

		$this->request('GET', '/v3/polls', [], [], ['Authorization' => 'Bearer test-token']);

		$this->assertResponseIsSuccessful();
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		$ids = array_column($data, 'id');
		$this->assertContains($activePoll->id, $ids);
		$this->assertNotContains($pastPoll->id, $ids);
	}

	/**
	 * @throws JsonException
	 */
	public function testGetActivePoll(): void
	{
		/** @var Poll $poll */
		$poll = $this->getFixture('active-poll', Poll::class);

		$this->request('GET', "/v3/polls/{$poll->id}", [], [], ['Authorization' => 'Bearer test-token']);

		$this->assertResponseStatusCodeSame(200);
		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame($poll->id, $data['id']);
		$this->assertSame($poll->question, $data['question']);
		$this->assertArrayHasKey('options', $data);
		$this->assertArrayHasKey('hasVoted', $data);
		$this->assertFalse($data['hasVoted']);
		$this->assertNull($data['votedOptionId']);
	}

	/**
	 * @throws JsonException
	 */
	public function testGetInactivePoll(): void
	{
		/** @var Poll $past */
		$past = $this->getFixture('past-poll', Poll::class);

		$this->request('GET', "/v3/polls/{$past->id}", [], [], ['Authorization' => 'Bearer test-token']);

		$this->assertResponseStatusCodeSame(404);
	}

	/**
	 * @throws JsonException
	 */
	public function testGetRequiresAuth(): void
	{
		/** @var Poll $poll */
		$poll = $this->getFixture('active-poll', Poll::class);

		$this->request('GET', "/v3/polls/{$poll->id}");

		$this->assertResponseStatusCodeSame(401);
	}

	/**
	 * @throws JsonException
	 */
	public function testVoteSuccess(): void
	{
		/** @var Poll $poll */
		$poll = $this->getFixture('active-poll', Poll::class);
		/** @var PollOption $yes */
		$yes = $this->getFixture('option-yes', PollOption::class);

		$this->request(
			'POST',
			"/v3/polls/{$poll->id}/vote",
			[],
			['optionId' => $yes->id],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(201);
	}

	/**
	 * @throws JsonException
	 */
	public function testVoteDuplicate(): void
	{
		/** @var Poll $poll */
		$poll = $this->getFixture('active-poll', Poll::class);
		/** @var PollOption $yes */
		$yes = $this->getFixture('option-yes', PollOption::class);

		$this->request(
			'POST',
			"/v3/polls/{$poll->id}/vote",
			[],
			['optionId' => $yes->id],
			['Authorization' => 'Bearer test-token']
		);
		$this->assertResponseStatusCodeSame(201);

		$this->request(
			'POST',
			"/v3/polls/{$poll->id}/vote",
			[],
			['optionId' => $yes->id],
			['Authorization' => 'Bearer test-token']
		);
		$this->assertResponseStatusCodeSame(409);
	}

	/**
	 * @throws JsonException
	 */
	public function testVoteInvalidOption(): void
	{
		/** @var Poll $poll */
		$poll = $this->getFixture('active-poll', Poll::class);

		$this->request(
			'POST',
			"/v3/polls/{$poll->id}/vote",
			[],
			['optionId' => 99999],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(400);
	}

	/**
	 * @throws JsonException
	 */
	public function testVoteInactivePoll(): void
	{
		/** @var Poll $past */
		$past = $this->getFixture('past-poll', Poll::class);

		$this->request(
			'POST',
			"/v3/polls/{$past->id}/vote",
			[],
			['optionId' => 1],
			['Authorization' => 'Bearer test-token']
		);

		$this->assertResponseStatusCodeSame(404);
	}

	/**
	 * @throws JsonException
	 */
	public function testHasVotedReflectedInList(): void
	{
		/** @var Poll $poll */
		$poll = $this->getFixture('active-poll', Poll::class);
		/** @var PollOption $yes */
		$yes = $this->getFixture('option-yes', PollOption::class);

		// Vote first
		$this->request(
			'POST',
			"/v3/polls/{$poll->id}/vote",
			[],
			['optionId' => $yes->id],
			['Authorization' => 'Bearer test-token']
		);
		$this->assertResponseStatusCodeSame(201);

		// List should reflect voted state
		$this->request('GET', '/v3/polls', [], [], ['Authorization' => 'Bearer test-token']);
		$this->assertResponseIsSuccessful();

		$data = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		$activePollData = null;
		foreach ($data as $item) {
			if ($item['id'] === $poll->id) {
				$activePollData = $item;
				break;
			}
		}

		$this->assertNotNull($activePollData);
		$this->assertTrue($activePollData['hasVoted']);
		$this->assertSame($yes->id, $activePollData['votedOptionId']);
	}
}
