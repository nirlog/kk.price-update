<?php

namespace KK\PriceUpdate\Exception;

final class PricingException extends \RuntimeException
{
    private const SAFE_FIELDS = [
        'code', 'group', 'xmlId', 'property', 'propertyCode', 'iblockId',
        'productId', 'priceTypeId', 'channel',
    ];

    /** @var array<string, int|float|string|bool> */
    private $diagnosticData;

    /**
     * The second argument remains compatible with RuntimeException's integer code.
     * It may alternatively contain the safe, structured diagnostic.
     *
     * @param array<string, mixed>|int $diagnosticOrCode
     */
    public function __construct(string $message, $diagnosticOrCode = [], ?\Throwable $previous = null)
    {
        $code = is_int($diagnosticOrCode) ? $diagnosticOrCode : 0;
        $diagnostic = is_array($diagnosticOrCode) ? self::sanitize($diagnosticOrCode) : [];
        if (!isset($diagnostic['code']) && self::safeValue($message) !== null) {
            $diagnostic['code'] = $message;
        }
        $this->diagnosticData = $diagnostic;
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, int|float|string|bool> */
    public function diagnostic(): array
    {
        return $this->diagnosticData;
    }

    /**
     * Converts an untrusted Throwable into a safe application exception and adds
     * adapter context without replacing a more specific source value.
     *
     * @param array<string, mixed> $context
     */
    public static function fromThrowable(\Throwable $exception, array $context = []): self
    {
        $diagnostic = [];
        if (method_exists($exception, 'diagnostic')) {
            try {
                $value = $exception->diagnostic();
                if (is_array($value)) {
                    $diagnostic = self::sanitize($value);
                }
            } catch (\Throwable $ignored) {
                // Use only the safe fallback below.
            }
        }
        if (!isset($diagnostic['code'])) {
            $diagnostic['code'] = self::safeValue($exception->getMessage())
                ?? 'korsac_price_calculation_failed';
        }
        $diagnostic += self::sanitize($context);

        return new self((string)$diagnostic['code'], $diagnostic, $exception);
    }

    /** @param array<string, mixed> $diagnostic */
    private static function sanitize(array $diagnostic): array
    {
        $safe = [];
        foreach (self::SAFE_FIELDS as $field) {
            if (!array_key_exists($field, $diagnostic)) {
                continue;
            }
            $value = self::safeValue($diagnostic[$field]);
            if ($value !== null) {
                $safe[$field] = $value;
            }
        }
        return $safe;
    }

    /** @return int|float|string|bool|null */
    private static function safeValue($value)
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' && preg_match('/^[A-Za-z0-9_.:-]+$/', $value) ? $value : null;
    }
}
