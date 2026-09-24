<?php
declare(strict_types=1);

namespace BBTChat\Provider;

/** A provider could not produce an answer. Message is safe to log, not to show a visitor. */
final class ProviderException extends \RuntimeException
{
}
