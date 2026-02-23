<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Pet\Application\Integration\Service\QbMockPullService;

final class QbMockPullServiceTest extends TestCase
{
    private \DI\Container $c;

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class extends \wpdb {
            public array $invoices = [];
            public array $payments = [];
            private array $lastInvoiceParams = [];
            private array $lastPaymentParams = [];

            public function __construct()
            {
                $this->prefix = 'wp_';
            }

            public function prepare($query, ...$args)
            {
                $params = [];
                if (count($args) === 1 && is_array($args[0])) {
                    $params = $args[0];
                } else {
                    $params = $args;
                }

                if (strpos($query, 'pet_qb_invoices') !== false) {
                    $this->lastInvoiceParams = $params;
                }
                if (strpos($query, 'pet_qb_payments') !== false) {
                    $this->lastPaymentParams = $params;
                }

                return $query;
            }

            public function query($query)
            {
                if (strpos($query, 'pet_qb_invoices') !== false && $this->lastInvoiceParams) {
                    $p = $this->lastInvoiceParams;
                    $id = (string)$p[1];
                    if (!isset($this->invoices[$id])) {
                        $this->invoices[$id] = [
                            'customer_id' => (int)$p[0],
                            'qb_invoice_id' => $id,
                            'doc_number' => $p[2],
                            'status' => $p[3],
                            'issue_date' => $p[4],
                            'due_date' => $p[5],
                            'currency' => $p[6],
                            'total' => (float)$p[7],
                            'balance' => (float)$p[8],
                            'raw_json' => (string)$p[9],
                            'last_synced_at' => (string)$p[10],
                        ];
                    } else {
                        $this->invoices[$id]['doc_number'] = $p[2];
                        $this->invoices[$id]['status'] = $p[3];
                        $this->invoices[$id]['currency'] = $p[6];
                        $this->invoices[$id]['total'] = (float)$p[7];
                        $this->invoices[$id]['balance'] = (float)$p[8];
                        $this->invoices[$id]['raw_json'] = (string)$p[9];
                        $this->invoices[$id]['last_synced_at'] = (string)$p[10];
                    }
                    return 1;
                }

                if (strpos($query, 'pet_qb_payments') !== false && $this->lastPaymentParams) {
                    $p = $this->lastPaymentParams;
                    $id = (string)$p[1];
                    if (!isset($this->payments[$id])) {
                        $this->payments[$id] = [
                            'customer_id' => (int)$p[0],
                            'qb_payment_id' => $id,
                            'received_date' => $p[2],
                            'amount' => (float)$p[3],
                            'currency' => $p[4],
                            'applied_invoices_json' => (string)$p[5],
                            'raw_json' => (string)$p[6],
                            'last_synced_at' => (string)$p[7],
                        ];
                    } else {
                        $this->payments[$id]['amount'] = (float)$p[3];
                        $this->payments[$id]['currency'] = $p[4];
                        $this->payments[$id]['applied_invoices_json'] = (string)$p[5];
                        $this->payments[$id]['raw_json'] = (string)$p[6];
                        $this->payments[$id]['last_synced_at'] = (string)$p[7];
                    }
                    return 1;
                }

                return 1;
            }

            public function get_var($query, $x = 0, $y = 0)
            {
                if (strpos($query, 'pet_qb_invoices') !== false) {
                    if (strpos($query, 'COUNT(*)') !== false) {
                        if ($this->lastInvoiceParams) {
                            $id = (string)$this->lastInvoiceParams[0];
                            return isset($this->invoices[$id]) ? 1 : 0;
                        }
                    }
                    if (strpos($query, 'last_synced_at') !== false) {
                        if ($this->lastInvoiceParams) {
                            $id = (string)$this->lastInvoiceParams[0];
                            return $this->invoices[$id]['last_synced_at'] ?? null;
                        }
                    }
                }

                if (strpos($query, 'pet_qb_payments') !== false) {
                    if (strpos($query, 'COUNT(*)') !== false) {
                        if ($this->lastPaymentParams) {
                            $id = (string)$this->lastPaymentParams[0];
                            return isset($this->payments[$id]) ? 1 : 0;
                        }
                    }
                    if (strpos($query, 'last_synced_at') !== false) {
                        if ($this->lastPaymentParams) {
                            $id = (string)$this->lastPaymentParams[0];
                            return $this->payments[$id]['last_synced_at'] ?? null;
                        }
                    }
                }

                return null;
            }
        };

        $this->c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
    }

    public function testPullInvoicesAndPaymentsAreIdempotent(): void
    {
        $svc = $this->c->get(QbMockPullService::class);
        $customerId = 999;
        global $wpdb;
        $invTable = $wpdb->prefix . 'pet_qb_invoices';
        $payTable = $wpdb->prefix . 'pet_qb_payments';

        $svc->pullInvoices($customerId);
        $svc->pullPayments($customerId);
        $countInv1 = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $invTable WHERE qb_invoice_id = %s", ['QB-PULL-' . $customerId]));
        $countPay1 = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $payTable WHERE qb_payment_id = %s", ['QB-PMT-' . $customerId]));
        $this->assertSame(1, $countInv1);
        $this->assertSame(1, $countPay1);
        $syncedInv1 = (string)$wpdb->get_var($wpdb->prepare("SELECT last_synced_at FROM $invTable WHERE qb_invoice_id = %s", ['QB-PULL-' . $customerId]));
        $syncedPay1 = (string)$wpdb->get_var($wpdb->prepare("SELECT last_synced_at FROM $payTable WHERE qb_payment_id = %s", ['QB-PMT-' . $customerId]));

        sleep(1);
        $svc->pullInvoices($customerId);
        $svc->pullPayments($customerId);
        $countInv2 = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $invTable WHERE qb_invoice_id = %s", ['QB-PULL-' . $customerId]));
        $countPay2 = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $payTable WHERE qb_payment_id = %s", ['QB-PMT-' . $customerId]));
        $this->assertSame(1, $countInv2);
        $this->assertSame(1, $countPay2);
        $syncedInv2 = (string)$wpdb->get_var($wpdb->prepare("SELECT last_synced_at FROM $invTable WHERE qb_invoice_id = %s", ['QB-PULL-' . $customerId]));
        $syncedPay2 = (string)$wpdb->get_var($wpdb->prepare("SELECT last_synced_at FROM $payTable WHERE qb_payment_id = %s", ['QB-PMT-' . $customerId]));
        $this->assertNotSame($syncedInv1, $syncedInv2);
        $this->assertNotSame($syncedPay1, $syncedPay2);
    }
}
