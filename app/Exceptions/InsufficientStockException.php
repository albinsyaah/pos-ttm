<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Thrown by StockService when a movement would take stock below zero.
 *
 * It extends ValidationException on purpose: Laravel already turns that
 * into "redirect back with errors and old input" (or a 422 for JSON), so a
 * controller does not need its own try/catch. The messages are attached to
 * the "items" key; views can show them with $errors->get('items').
 */
class InsufficientStockException extends ValidationException
{
    /**
     * @var array<int, string>
     */
    public array $shortages = [];

    /**
     * @param  array<int, string>  $messages  One human-readable message per short product.
     */
    public static function forShortages(array $messages): static
    {
        $exception = static::withMessages(['items' => array_values($messages)]);
        $exception->shortages = array_values($messages);

        return $exception;
    }
}
