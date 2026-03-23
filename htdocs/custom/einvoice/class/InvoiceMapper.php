<?php
/**
 * Invoice Mapper
 * Maps Dolibarr invoice data to TEIF format
 */

namespace Einvoice\Class;

use Exception;

class InvoiceMapper
{
    private $auditLogger;
    private $currencyExchangeRates;

    private const REQUIRED_FIELDS = [
        'invoice_id',
        'invoice_date',
        'seller_name',
        'seller_tax_id',
        'buyer_name',
        'buyer_tax_id',
        'total_ht',
        'total_vat',
        'total_ttc',
    ];

    public function __construct($auditLogger = null, array $exchangeRates = [])
    {
        $this->auditLogger = $auditLogger;
        $this->currencyExchangeRates = $exchangeRates;
    }

    /**
     * Map Dolibarr invoice to TEIF format
     *
     * @param array $dolibarrInvoice Dolibarr invoice data
     * @param array $companyInfo Company information
     * @param array $customerInfo Customer information
     * @return array TEIF formatted invoice
     * @throws Exception
     */
    public function mapDolibarrToTEIF(
        array $dolibarrInvoice,
        array $companyInfo,
        array $customerInfo
    ): array {
        try {
            // Validate mapping rules
            $this->validateMappingRules($dolibarrInvoice, $companyInfo, $customerInfo);

            $teifInvoice = [
                'invoice_id' => $dolibarrInvoice['ref'] ?? '',
                'invoice_number' => $dolibarrInvoice['number'] ?? '',
                'issue_date' => $this->formatDate($dolibarrInvoice['date']),
                'due_date' => isset($dolibarrInvoice['date_due']) 
                    ? $this->formatDate($dolibarrInvoice['date_due']) 
                    : null,
                'invoice_type' => $this->mapInvoiceType($dolibarrInvoice['type'] ?? 'STANDARD'),
                'currency' => $dolibarrInvoice['currency'] ?? 'TND',
                // Seller information
                'seller' => [
                    'name' => $companyInfo['name'] ?? '',
                    'tax_id' => $companyInfo['tax_id'] ?? $companyInfo['siret'] ?? '',
                    'address' => $companyInfo['address'] ?? '',
                    'city' => $companyInfo['city'] ?? '',
                    'postal_code' => $companyInfo['postal_code'] ?? '',
                    'country' => $companyInfo['country'] ?? 'TN',
                    'email' => $companyInfo['email'] ?? '',
                    'phone' => $companyInfo['phone'] ?? '',
                ],
                // Buyer information
                'buyer' => [
                    'name' => $customerInfo['name'] ?? '',
                    'tax_id' => $customerInfo['tax_id'] ?? $customerInfo['siret'] ?? '',
                    'address' => $customerInfo['address'] ?? '',
                    'city' => $customerInfo['city'] ?? '',
                    'postal_code' => $customerInfo['postal_code'] ?? '',
                    'country' => $customerInfo['country'] ?? 'TN',
                    'email' => $customerInfo['email'] ?? '',
                    'phone' => $customerInfo['phone'] ?? '',
                ],
                // Line items
                'lines' => $this->extractLineItems($dolibarrInvoice['lines'] ?? []),
                // Financial data
                'financial' => $this->calculateTaxes($dolibarrInvoice),
                // Metadata
                'notes' => $dolibarrInvoice['note'] ?? '',
                'payment_terms' => $dolibarrInvoice['payment_terms'] ?? '',
            ];

            // Handle multi-currency
            if ($teifInvoice['currency'] !== 'TND') {
                $teifInvoice = $this->handleMultipleCurrencies($teifInvoice);
            }

            // Handle special cases
            if (isset($dolibarrInvoice['type']) && $dolibarrInvoice['type'] === 'EXPORT') {
                $teifInvoice = $this->handleExportInvoice($teifInvoice);
            }

            if (isset($dolibarrInvoice['type']) && $dolibarrInvoice['type'] === 'CREDIT_NOTE') {
                $teifInvoice = $this->handleCreditNote($teifInvoice);
            }

            if ($this->auditLogger) {
                $this->auditLogger->logInfo(
                    'Invoice mapped to TEIF format',
                    ['invoice_id' => $teifInvoice['invoice_id']]
                );
            }

            return $teifInvoice;

        } catch (Exception $e) {
            if ($this->auditLogger) {
                $this->auditLogger->logError(
                    'Invoice mapping failed: ' . $e->getMessage(),
                    ['invoice_ref' => $dolibarrInvoice['ref'] ?? 'unknown']
                );
            }
            throw $e;
        }
    }

    /**
     * Validate mapping rules
     *
     * @param array $invoice Invoice data
     * @param array $company Company info
     * @param array $customer Customer info
     * @return bool True if valid
     * @throws Exception
     */
    public function validateMappingRules(array $invoice, array $company, array $customer): bool
    {
        $errors = [];

        // Check required fields in invoice
        foreach (['ref', 'date'] as $field) {
            if (empty($invoice[$field])) {
                $errors[] = "Invoice missing required field: {$field}";
            }
        }

        // Check required fields in company
        foreach (['name', 'tax_id'] as $field) {
            if (empty($company[$field])) {
                $errors[] = "Company missing required field: {$field}";
            }
        }

        // Check required fields in customer
        foreach (['name'] as $field) {
            if (empty($customer[$field])) {
                $errors[] = "Customer missing required field: {$field}";
            }
        }

        // Check financial data
        if (empty($invoice['total_ht']) && empty($invoice['total_ttc'])) {
            $errors[] = "Invoice missing total amount (total_ht or total_ttc)";
        }

        if (!empty($errors)) {
            throw new Exception("Mapping validation failed:\n" . implode("\n", $errors));
        }

        return true;
    }

    /**
     * Extract and format line items
     *
     * @param array $lines Invoice lines
     * @return array Formatted lines
     */
    public function extractLineItems(array $lines): array
    {
        $formatted = [];
        $lineNumber = 1;

        foreach ($lines as $line) {
            $formatted[] = [
                'line_number' => $lineNumber++,
                'description' => $line['description'] ?? '',
                'quantity' => (float)($line['qty'] ?? 1),
                'unit_price' => (float)($line['unit_price'] ?? 0),
                'total_ht' => (float)($line['total_ht'] ?? 0),
                'vat_rate' => (float)($line['tva_tx'] ?? 0),
                'vat_amount' => (float)($line['total_tva'] ?? 0),
                'total_ttc' => (float)($line['total_ttc'] ?? 0),
            ];
        }

        return $formatted;
    }

    /**
     * Calculate taxes with complex VAT rules
     *
     * @param array $invoice Invoice data
     * @return array Financial summary
     */
    public function calculateTaxes(array $invoice): array
    {
        $totalHT = (float)($invoice['total_ht'] ?? 0);
        $totalVAT = (float)($invoice['total_tva'] ?? 0);
        $totalTTC = (float)($invoice['total_ttc'] ?? 0);

        // Validate arithmetic
        if (abs($totalTTC - ($totalHT + $totalVAT)) > 0.01) {
            if ($this->auditLogger) {
                $this->auditLogger->logWarning(
                    'Tax calculation mismatch detected',
                    [
                        'total_ht' => $totalHT,
                        'total_vat' => $totalVAT,
                        'total_ttc' => $totalTTC,
                        'expected_ttc' => $totalHT + $totalVAT,
                    ]
                );
            }
        }

        // Group by VAT rate
        $vatByRate = [];
        foreach ($invoice['lines'] ?? [] as $line) {
            $vatRate = (float)($line['tva_tx'] ?? 0);
            
            if (!isset($vatByRate[$vatRate])) {
                $vatByRate[$vatRate] = [
                    'rate' => $vatRate,
                    'base' => 0,
                    'amount' => 0,
                ];
            }

            $vatByRate[$vatRate]['base'] += (float)($line['total_ht'] ?? 0);
            $vatByRate[$vatRate]['amount'] += (float)($line['total_tva'] ?? 0);
        }

        return [
            'total_ht' => $this->roundCurrency($totalHT),
            'total_vat' => $this->roundCurrency($totalVAT),
            'total_ttc' => $this->roundCurrency($totalTTC),
            'vat_breakdown' => $vatByRate,
            'discount' => (float)($invoice['remise'] ?? 0),
        ];
    }

    /**
     * Handle multiple currencies
     *
     * @param array $teifInvoice TEIF invoice
     * @return array Converted invoice
     */
    public function handleMultipleCurrencies(array $teifInvoice): array
    {
        $sourceCurrency = $teifInvoice['currency'];
        $targetCurrency = 'TND';

        if ($sourceCurrency === $targetCurrency) {
            return $teifInvoice;
        }

        $exchangeRate = $this->getExchangeRate($sourceCurrency, $targetCurrency);

        // Apply exchange rate to financial data
        $teifInvoice['financial']['total_ht'] *= $exchangeRate;
        $teifInvoice['financial']['total_vat'] *= $exchangeRate;
        $teifInvoice['financial']['total_ttc'] *= $exchangeRate;

        // Update line items
        foreach ($teifInvoice['lines'] as &$line) {
            $line['unit_price'] *= $exchangeRate;
            $line['total_ht'] *= $exchangeRate;
            $line['vat_amount'] *= $exchangeRate;
            $line['total_ttc'] *= $exchangeRate;
        }

        $teifInvoice['currency'] = $targetCurrency;
        $teifInvoice['exchange_rate'] = $exchangeRate;
        $teifInvoice['original_currency'] = $sourceCurrency;

        return $teifInvoice;
    }

    /**
     * Handle export invoices (0% VAT)
     *
     * @param array $teifInvoice TEIF invoice
     * @return array Modified invoice
     */
    private function handleExportInvoice(array $teifInvoice): array
    {
        $teifInvoice['financial']['total_vat'] = 0;
        $teifInvoice['financial']['total_ttc'] = $teifInvoice['financial']['total_ht'];

        foreach ($teifInvoice['lines'] as &$line) {
            $line['vat_rate'] = 0;
            $line['vat_amount'] = 0;
            $line['total_ttc'] = $line['total_ht'];
        }

        $teifInvoice['export'] = true;

        return $teifInvoice;
    }

    /**
     * Handle credit notes
     *
     * @param array $teifInvoice TEIF invoice
     * @return array Modified invoice
     */
    private function handleCreditNote(array $teifInvoice): array
    {
        // Negate amounts for credit notes
        $teifInvoice['financial']['total_ht'] *= -1;
        $teifInvoice['financial']['total_vat'] *= -1;
        $teifInvoice['financial']['total_ttc'] *= -1;

        foreach ($teifInvoice['lines'] as &$line) {
            $line['quantity'] *= -1;
            $line['total_ht'] *= -1;
            $line['vat_amount'] *= -1;
            $line['total_ttc'] *= -1;
        }

        $teifInvoice['credit_note'] = true;

        return $teifInvoice;
    }

    // Private helper methods

    private function mapInvoiceType(string $dolibarrType): string
    {
        $typeMap = [
            'STANDARD' => 'STANDARD',
            'EXPORT' => 'EXPORT',
            'CREDIT_NOTE' => 'CREDIT_NOTE',
            'DEPOSIT' => 'DEPOSIT',
        ];

        return $typeMap[$dolibarrType] ?? 'STANDARD';
    }

    private function formatDate($date): string
    {
        if (is_numeric($date)) {
            return date('Y-m-d', $date);
        }

        return (new \DateTime($date))->format('Y-m-d');
    }

    private function getExchangeRate(string $from, string $to): float
    {
        $key = "{$from}_{$to}";

        if (isset($this->currencyExchangeRates[$key])) {
            return $this->currencyExchangeRates[$key];
        }

        // Default to 1.0 if rate not found
        return 1.0;
    }

    private function roundCurrency(float $value, int $decimals = 3): float
    {
        return round($value, $decimals, PHP_ROUND_HALF_UP);
    }
}