<?php

namespace App\Services\Reports\Accounting;

trait AccountClassificationTrait
{
    protected function classifyAccountClass(string $classCode, string $className): string
    {
        $code = trim($classCode);
        if ($code === '1') {
            return 'assets';
        }
        if ($code === '2') {
            return 'liabilities';
        }
        if ($code === '3') {
            return 'equity';
        }
        if ($code === '4') {
            return 'income';
        }
        if ($code === '5') {
            return 'expense';
        }

        $name = strtolower(trim($className));
        if (str_contains($name, 'asset')) {
            return 'assets';
        }
        if (str_contains($name, 'liabil')) {
            return 'liabilities';
        }
        if (str_contains($name, 'equity')) {
            return 'equity';
        }
        if (str_contains($name, 'income')) {
            return 'income';
        }
        if (str_contains($name, 'expense')) {
            return 'expense';
        }

        return 'unclassified';
    }
}