<?php

declare(strict_types=1);

namespace Mailkube;

/**
 * The documented ``name`` values of the API error envelope.
 *
 * These are constants for discoverability, **not** a closed set: ``ApiException::$errorName``
 * stays a plain string at runtime, so a name this release has never heard of is reported
 * verbatim instead of failing to construct. Compare with
 * ``$exception->errorName === ErrorName::QuotaExceeded->value``. Add a case when the public
 * error reference gains a name.
 */
enum ErrorName: string
{
    case ApplicationError = 'application_error';
    case BodyContentRejected = 'body_content_rejected';
    case BrowserNotAllowed = 'browser_not_allowed';
    case ConcurrentIdempotentRequests = 'concurrent_idempotent_requests';
    case FromDomainNotAllowed = 'from_domain_not_allowed';
    case InvalidApiKey = 'invalid_api_key';
    case InvalidAttachment = 'invalid_attachment';
    case InvalidFromAddress = 'invalid_from_address';
    case InvalidIdempotencyKey = 'invalid_idempotency_key';
    case InvalidIdempotentRequest = 'invalid_idempotent_request';
    case InvalidRequestBody = 'invalid_request_body';
    case LinkReputationBlocked = 'link_reputation_blocked';
    case MaxMessageSizeExceeded = 'max_message_size_exceeded';
    case MaxRecipientsExceeded = 'max_recipients_exceeded';
    case MethodNotAllowed = 'method_not_allowed';
    case MissingRequiredField = 'missing_required_field';
    case MissingRequiredVariable = 'missing_required_variable';
    case MissingUserAgent = 'missing_user_agent';
    case NotAcceptable = 'not_acceptable';
    case QuotaExceeded = 'quota_exceeded';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ScheduledEmailNotFound = 'scheduled_email_not_found';
    case ScheduledEmailNotPending = 'scheduled_email_not_pending';
    case SchedulingNotIncluded = 'scheduling_not_included';
    case TemplateNotFound = 'template_not_found';
    case TemplateNotPublished = 'template_not_published';
    case TopicDisabled = 'topic_disabled';
    case TopicNotFound = 'topic_not_found';
    case UnsupportedMediaType = 'unsupported_media_type';
    case ValidationError = 'validation_error';
}
