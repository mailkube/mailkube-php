<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\Exception\MailkubeException;
use Mailkube\Model\ScheduledEmail;

/**
 * Walking pages by following the links the server issues.
 */
final class PaginationTest extends TestCase
{
    /**
     * Build one page payload, omitting `steps.next` at the end of the range like the API does.
     */
    private static function page(int $from, int $count, ?string $next = null, int $currentPage = 1): string
    {
        $rows = [];
        for ($index = $from; $index < $from + $count; $index++) {
            $rows[] = ['id' => "id-{$index}", 'status' => 'scheduled'];
        }

        return (string) json_encode([
            'pagination' => [
                'steps' => $next === null ? [] : ['next' => $next],
                'total_count' => 9,
                'current_page' => $currentPage,
            ],
            'data' => $rows,
        ]);
    }

    public function testHasMoreReflectsWhetherTheServerOfferedANextLink(): void
    {
        $next = self::BASE_URL . 'scheduled-emails?page=2';

        self::assertTrue($this->client(200, self::page(1, 2, $next))->scheduledEmails->list()->hasMore());

        $this->setUp();
        self::assertFalse($this->client(200, self::page(1, 2))->scheduledEmails->list()->hasMore());
    }

    public function testIterAllWalksEveryPageInOrder(): void
    {
        $this->queue(200, self::page(1, 2, self::BASE_URL . 'scheduled-emails?page=2'));
        $this->queue(200, self::page(3, 2, self::BASE_URL . 'scheduled-emails?page=3', 2));
        $this->queue(200, self::page(5, 1, null, 3));

        $ids = [];
        foreach ($this->clientOverQueue()->scheduledEmails->iterAll() as $email) {
            $ids[] = $email->id;
        }

        self::assertSame(['id-1', 'id-2', 'id-3', 'id-4', 'id-5'], $ids);
        self::assertCount(3, $this->sentRequests());
        self::assertSame(
            self::BASE_URL . 'scheduled-emails?page=3',
            (string) $this->sentRequests()[2]->getUri(),
        );
    }

    public function testMaterializingTheIteratorKeepsEveryPage(): void
    {
        // The assertion that catches a `yield from`: it preserves each page's own 0..n-1 keys, so
        // iterator_to_array() would silently return only the last page's rows.
        $this->queue(200, self::page(1, 2, self::BASE_URL . 'scheduled-emails?page=2'));
        $this->queue(200, self::page(3, 2, self::BASE_URL . 'scheduled-emails?page=3', 2));
        $this->queue(200, self::page(5, 1, null, 3));

        $all = iterator_to_array($this->clientOverQueue()->scheduledEmails->iterAll());

        self::assertCount(5, $all);
        self::assertContainsOnlyInstancesOf(ScheduledEmail::class, $all);
        self::assertSame([0, 1, 2, 3, 4], array_keys($all));
    }

    public function testFiltersAreAppliedToTheFirstPageOnly(): void
    {
        // Later pages come from the server's own link, which already carries the filters.
        $this->queue(200, self::page(1, 1, self::BASE_URL . 'scheduled-emails?status=scheduled&page=2'));
        $this->queue(200, self::page(2, 1, null, 2));

        iterator_to_array($this->clientOverQueue()->scheduledEmails->iterAll(status: 'scheduled'));

        $requests = $this->sentRequests();
        self::assertSame('status=scheduled', $requests[0]->getUri()->getQuery());
        self::assertSame('status=scheduled&page=2', $requests[1]->getUri()->getQuery());
    }

    public function testAbandoningTheIteratorEarlyIssuesNoFurtherRequest(): void
    {
        $this->queue(200, self::page(1, 2, self::BASE_URL . 'scheduled-emails?page=2'));

        foreach ($this->clientOverQueue()->scheduledEmails->iterAll() as $email) {
            self::assertSame('id-1', $email->id);

            break;
        }

        self::assertCount(1, $this->sentRequests(), 'the second page must not be fetched');
    }

    public function testASinglePageTerminatesWithoutASecondRequest(): void
    {
        $this->queue(200, self::page(1, 2));

        self::assertCount(2, iterator_to_array($this->clientOverQueue()->scheduledEmails->iterAll()));
        self::assertCount(1, $this->sentRequests());
    }

    public function testAnEmptyPageParsesAndYieldsNothing(): void
    {
        // The server omits total_count/current_page when the backing store did not report them.
        $page = $this->client(200, '{"pagination":{"steps":{}},"data":[]}')->scheduledEmails->list();

        self::assertSame([], $page->data);
        self::assertFalse($page->hasMore());
        self::assertSame(0, $page->pagination->totalCount);
        self::assertSame(1, $page->pagination->currentPage);
        self::assertNull($page->pagination->steps->previous);
    }

    public function testANextLinkOnAForeignOriginIsRefusedNotFollowed(): void
    {
        // A credentialed request must never be redirected off the configured API origin.
        $this->queue(200, self::page(1, 1, 'https://evil.example.com/mta/v1/scheduled-emails?page=2'));

        $this->expectException(MailkubeException::class);
        $this->expectExceptionMessage('not on the configured API origin');

        iterator_to_array($this->clientOverQueue()->scheduledEmails->iterAll());
    }
}
