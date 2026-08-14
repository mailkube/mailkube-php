<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\ErrorName;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * The error-name catalogue matches the documented set.
 */
final class ErrorNameTest extends BaseTestCase
{
    /**
     * Every name in the public error reference, written out.
     *
     * Hand-maintained on purpose. Deriving it from the enum would assert nothing, and neither the
     * docs repo nor a sibling SDK is available to this repository's CI, so this literal is the only
     * thing that can catch a dropped or misspelled case.
     *
     * @phpstan-return list<string>
     */
    private static function documented(): array
    {
        return [
            'application_error',
            'body_content_rejected',
            'browser_not_allowed',
            'concurrent_idempotent_requests',
            'from_domain_not_allowed',
            'invalid_api_key',
            'invalid_attachment',
            'invalid_from_address',
            'invalid_idempotency_key',
            'invalid_idempotent_request',
            'invalid_request_body',
            'link_reputation_blocked',
            'max_message_size_exceeded',
            'max_recipients_exceeded',
            'method_not_allowed',
            'missing_required_field',
            'missing_required_variable',
            'missing_user_agent',
            'not_acceptable',
            'quota_exceeded',
            'rate_limit_exceeded',
            'scheduled_email_not_found',
            'scheduled_email_not_pending',
            'scheduling_not_included',
            'template_not_found',
            'template_not_published',
            'topic_disabled',
            'topic_not_found',
            'unsupported_media_type',
            'validation_error',
        ];
    }

    public function testTheEnumCarriesEveryDocumentedName(): void
    {
        self::assertSame(self::documented(), array_column(ErrorName::cases(), 'value'));
    }

    public function testTheCasesStaySorted(): void
    {
        $values = array_column(ErrorName::cases(), 'value');
        $sorted = $values;
        sort($sorted);

        self::assertSame($sorted, $values);
    }
}
