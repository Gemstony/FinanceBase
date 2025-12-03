<?php

namespace App\Services;

use App\Models\PrinterSetting;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use App\Models\SalesOrders;
use App\Models\SalesReturns;
use App\Models\Transaction;
use App\Models\PurchaseOrders;
use App\Models\PurchaseReturns;

class ReceiptPrinter
{
    /**
     * Print a small test receipt to verify connectivity and basic formatting
     */
    public function printTest(PrinterSetting $printerSetting, bool $dummy = false): ?string
    {
        if ($dummy) {
            $lines = [
                "TEST PRINT",
                str_repeat('-', 30),
                'Shop: ' . ($printerSetting->subshop ? $printerSetting->subshop->name : 'Unknown'),
                'Printer: ' . ($printerSetting->name ?: 'Unnamed'),
                'IP: ' . $printerSetting->ip_address . ':' . (int) $printerSetting->port,
                'Time: ' . now()->format('Y-m-d H:i:s'),
                str_repeat('-', 30),
                'OK',
                '',
            ];
            $raw = implode("\n", $lines) . "\n\n";
            return base64_encode($raw);
        }

        $connector = new NetworkPrintConnector($printerSetting->ip_address, (int) $printerSetting->port, 5);
        $printer = new Printer($connector);
        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $printer->text("TEST PRINT\n");
            $printer->selectPrintMode();
            $printer->text("------------------------------\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Shop: ".(($printerSetting->subshop ? $printerSetting->subshop->name : 'Unknown'))."\n");
            $printer->text("Printer: ".($printerSetting->name ?: 'Unnamed')."\n");
            $printer->text("IP: {$printerSetting->ip_address}:".(int)$printerSetting->port."\n");
            $printer->text("Time: ".now()->format('Y-m-d H:i:s')."\n");
            $printer->text("------------------------------\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("OK\n\n");
            $printer->feed(2);
            $printer->cut();
        } finally {
            $printer->close();
        }
        return null;
    }

    /**
     * Print a Purchase Return receipt (single-line return) or return dummy text
     */
    public function printPurchaseReturn(PurchaseReturns $return, PrinterSetting $printer, bool $dummy = false): ?string
    {
        $order = PurchaseOrders::with(['supplier','creator','subshop'])->findOrFail($return->purchase_order_id);
        // Build line info
        $itemName = null; $unit = null;
        $poi = \App\Models\PurchaseOrdersItems::where('id', $return->purchase_order_item_id)->first();
        if ($poi) { $itemName = $poi->item_name; $unit = $poi->unit; }
        elseif ($return->item_id) { $it = \App\Models\Item::withTrashed()->find($return->item_id); if ($it) { $itemName = $it->name; $unit = $it->unit; } }
        if (!$itemName) { $itemName = $return->item_id ? ('Item #'.$return->item_id) : 'Item'; }
        $refund = $return->transaction_id ? \App\Models\PurchasesTransactions::find($return->transaction_id) : null;
        $refundAmt = $refund ? (float)$refund->total_amount : 0.0; // negative for refund
        $qrData = 'PO-RET-' . $order->order_no . '-' . $return->id;

        if ($dummy) {
            $lines = [];
            $lines[] = $order->subshop ? strtoupper($order->subshop->name) : 'SHOP';
            $lines[] = 'PURCHASE RETURN';
            $lines[] = 'PO: ' . $order->order_no;
            $lines[] = now()->format('Y-m-d H:i');
            $lines[] = str_repeat('-', 32);
            $lines[] = 'Supplier: ' . (optional($order->supplier)->name ?? '-');
            $lines[] = str_repeat('-', 32);
            $lines[] = $itemName;
            $lines[] = sprintf('  %dx%s  %s', (int)$return->quantity_returned, number_format((float)$return->unit_price,2), number_format((float)$return->line_total,2));
            $lines[] = str_repeat('-', 32);
            $lines[] = sprintf('%-12s %s', 'Base:', number_format((float)$return->base_amount,2));
            $lines[] = sprintf('%-12s %s', 'VAT:', number_format((float)$return->vat_amount,2));
            $lines[] = sprintf('%-12s %s', 'Returned:', number_format((float)$return->line_total,2));
            if ($refund) {
                $lines[] = sprintf('%-12s %s', 'Refund:', number_format(abs($refundAmt),2)) . ($refund->payment_method ? (' ('.strtoupper($refund->payment_method).')') : '');
            }
            $lines[] = str_repeat('-', 32);
            $lines[] = 'QR: ' . $qrData;
            $lines[] = 'Thank you!';
            $raw = implode("\n", $lines) . "\n\n";
            return base64_encode($raw);
        }

        $connector = new NetworkPrintConnector($printer->ip_address, (int)$printer->port, 5);
        $p = new Printer($connector);
        try {
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $p->text(($order->subshop ? strtoupper($order->subshop->name) : 'SHOP') . "\n");
            $p->selectPrintMode();
            $p->text("PURCHASE RETURN\n");
            $p->text('PO: ' . $order->order_no . "\n");
            $p->text(now()->format('Y-m-d H:i') . "\n");
            $p->text("------------------------------\n");
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text('Supplier: ' . (optional($order->supplier)->name ?? '-') . "\n");
            $p->text("------------------------------\n");
            $p->text($itemName . "\n");
            $p->text(sprintf('  %dx%s', (int)$return->quantity_returned, number_format((float)$return->unit_price,2)));
            $p->setJustification(Printer::JUSTIFY_RIGHT);
            $p->text('  ' . number_format((float)$return->line_total,2) . "\n");
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text("------------------------------\n");
            $p->text(sprintf('%-10s %s', 'Base:', number_format((float)$return->base_amount,2)) . "\n");
            $p->text(sprintf('%-10s %s', 'VAT:', number_format((float)$return->vat_amount,2)) . "\n");
            $p->selectPrintMode(Printer::MODE_EMPHASIZED);
            $p->text('Returned: ' . number_format((float)$return->line_total,2) . "\n");
            $p->selectPrintMode();
            if ($refund) { $p->text('Refund: ' . number_format(abs($refundAmt),2) . ($refund->payment_method ? (' ('.strtoupper($refund->payment_method).')') : '') . "\n"); }
            $p->text("------------------------------\n");
            try { $p->qrCode($qrData); $p->feed(1); } catch (\Throwable $e) { }
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->text("Thank you!\n\n");
            $p->feed(2);
            $p->cut();
        } finally { $p->close(); }
        return null;
    }

    /**
     * Print a Purchase Order receipt or return dummy text
     */
    public function printPurchase(PurchaseOrders $order, PrinterSetting $printer, bool $dummy = false): ?string
    {
        $order->loadMissing(['items','supplier','creator','subshop']);
        // compute paid/remaining for purchases
        $paid = (float) \App\Models\PurchasesTransactions::where('purchase_order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);
        $qrData = 'PO-' . $order->order_no;

        if ($dummy) {
            $lines = [];
            $lines[] = $order->subshop ? strtoupper($order->subshop->name) : 'SHOP';
            $lines[] = 'PURCHASE ORDER';
            $lines[] = 'PO #' . $order->order_no;
            $lines[] = now()->format('Y-m-d H:i');
            $lines[] = str_repeat('-', 32);
            if ($order->supplier) { $lines[] = 'Supplier: ' . $order->supplier->name; }
            $lines[] = str_repeat('-', 32);
            foreach ($order->items as $it) {
                $name = $it->item_name ?? ($it->item->name ?? 'Item');
                $qty = (int)$it->quantity;
                $price = number_format((float)$it->unit_price, 2);
                $total = number_format((float)$it->line_total, 2);
                $lines[] = $name;
                $lines[] = sprintf('  %dx%s  %s', $qty, $price, $total);
            }
            $lines[] = str_repeat('-', 32);
            $lines[] = sprintf('%-16s %16s', 'Subtotal:', number_format((float)$order->subtotal, 2));
            $lines[] = sprintf('%-16s %16s', 'VAT:', number_format((float)$order->vat_total, 2));
            $lines[] = sprintf('%-16s %16s', 'Discount:', number_format((float)$order->discount_total, 2));
            $lines[] = sprintf('%-16s %16s', 'Grand:', number_format((float)$order->grand_total, 2));
            $lines[] = sprintf('%-16s %16s', 'Paid:', number_format($paid, 2));
            $lines[] = sprintf('%-16s %16s', 'Remaining:', number_format($remaining, 2));
            $lines[] = str_repeat('-', 32);
            $lines[] = 'QR: ' . $qrData;
            $lines[] = 'Thank you!';
            $raw = implode("\n", $lines) . "\n\n";
            return base64_encode($raw);
        }

        $connector = new NetworkPrintConnector($printer->ip_address, (int)$printer->port, 5);
        $p = new Printer($connector);
        try {
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $p->text(($order->subshop ? strtoupper($order->subshop->name) : 'SHOP') . "\n");
            $p->selectPrintMode();
            $p->text('PURCHASE ORDER' . "\n");
            $p->text('PO #' . $order->order_no . "\n");
            $p->text(now()->format('Y-m-d H:i') . "\n");
            $p->text("------------------------------\n");
            if ($order->supplier) { $p->setJustification(Printer::JUSTIFY_LEFT); $p->text('Supplier: ' . $order->supplier->name . "\n"); }
            $p->text("------------------------------\n");
            foreach ($order->items as $it) {
                $p->setJustification(Printer::JUSTIFY_LEFT);
                $p->text(($it->item_name ?? 'Item') . "\n");
                $line = sprintf('  %dx%s', (int)$it->quantity, number_format((float)$it->unit_price, 2));
                $p->text($line);
                $p->setJustification(Printer::JUSTIFY_RIGHT);
                $p->text('  ' . number_format((float)$it->line_total, 2) . "\n");
            }
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text("------------------------------\n");
            $p->text(sprintf('%-12s %s', 'Subtotal:', number_format((float)$order->subtotal, 2)) . "\n");
            $p->text(sprintf('%-12s %s', 'VAT:', number_format((float)$order->vat_total, 2)) . "\n");
            $p->text(sprintf('%-12s %s', 'Discount:', number_format((float)$order->discount_total, 2)) . "\n");
            $p->setJustification(Printer::JUSTIFY_RIGHT);
            $p->selectPrintMode(Printer::MODE_EMPHASIZED);
            $p->text('Grand: ' . number_format((float)$order->grand_total, 2) . "\n");
            $p->selectPrintMode();
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text(sprintf('%-12s %s', 'Paid:', number_format($paid, 2)) . "\n");
            $p->text(sprintf('%-12s %s', 'Remaining:', number_format($remaining, 2)) . "\n");
            $p->text("------------------------------\n");
            try { $p->qrCode($qrData); $p->feed(1); } catch (\Throwable $e) { }
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->text("Thank you!\n\n");
            $p->feed(2);
            $p->cut();
        } finally { $p->close(); }
        return null;
    }

    /**
     * Print a Sales Return receipt (single-line return) or return dummy text
     */
    public function printReturn(SalesReturns $return, PrinterSetting $printer, bool $dummy = false): ?string
    {
        $order = SalesOrders::with(['customer','creator','subshop'])->findOrFail($return->sales_order_id);
        // Build line info
        $itemName = null; $unit = null;
        $soi = \App\Models\SalesOrdersItems::where('id', $return->sales_order_item_id)->first();
        if ($soi) { $itemName = $soi->item_name; $unit = $soi->unit; }
        elseif ($return->item_id) { $it = \App\Models\Item::withTrashed()->find($return->item_id); if ($it) { $itemName = $it->name; $unit = $it->unit; } }
        if (!$itemName) { $itemName = $return->item_id ? ('Item #'.$return->item_id) : 'Item'; }
        $refund = $return->transaction_id ? Transaction::find($return->transaction_id) : null;
        $refundAmt = $refund ? (float)$refund->total_amount : 0.0; // negative for refund
        $qrData = $order->order_no . '-RET-' . $return->id;

        if ($dummy) {
            $lines = [];
            $lines[] = $order->subshop ? strtoupper($order->subshop->name) : 'SHOP';
            $lines[] = 'SALES RETURN';
            $lines[] = 'Sale: ' . $order->order_no;
            $lines[] = now()->format('Y-m-d H:i');
            $lines[] = str_repeat('-', 32);
            $lines[] = 'Customer: ' . (optional($order->customer)->name ?? '-');
            $lines[] = str_repeat('-', 32);
            $lines[] = $itemName;
            $lines[] = sprintf('  %dx%s  %s', (int)$return->quantity_returned, number_format((float)$return->unit_price,2), number_format((float)$return->line_total,2));
            $lines[] = str_repeat('-', 32);
            $lines[] = sprintf('%-12s %s', 'Base:', number_format((float)$return->base_amount,2));
            $lines[] = sprintf('%-12s %s', 'VAT:', number_format((float)$return->vat_amount,2));
            $lines[] = sprintf('%-12s %s', 'Returned:', number_format((float)$return->line_total,2));
            if ($refund) {
                $lines[] = sprintf('%-12s %s', 'Refund:', number_format(abs($refundAmt),2)) . ($refund->payment_method ? (' ('.strtoupper($refund->payment_method).')') : '');
            }
            $lines[] = str_repeat('-', 32);
            $lines[] = 'QR: ' . $qrData;
            $lines[] = 'Thank you!';
            $raw = implode("\n", $lines) . "\n\n";
            return base64_encode($raw);
        }

        $connector = new NetworkPrintConnector($printer->ip_address, (int)$printer->port, 5);
        $p = new Printer($connector);
        try {
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $p->text(($order->subshop ? strtoupper($order->subshop->name) : 'SHOP') . "\n");
            $p->selectPrintMode();
            $p->text("SALES RETURN\n");
            $p->text('Sale: ' . $order->order_no . "\n");
            $p->text(now()->format('Y-m-d H:i') . "\n");
            $p->text("------------------------------\n");
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text('Customer: ' . (optional($order->customer)->name ?? '-') . "\n");
            $p->text("------------------------------\n");
            $p->text($itemName . "\n");
            $p->text(sprintf('  %dx%s', (int)$return->quantity_returned, number_format((float)$return->unit_price,2)));
            $p->setJustification(Printer::JUSTIFY_RIGHT);
            $p->text('  ' . number_format((float)$return->line_total,2) . "\n");
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text("------------------------------\n");
            $p->text(sprintf('%-10s %s', 'Base:', number_format((float)$return->base_amount,2)) . "\n");
            $p->text(sprintf('%-10s %s', 'VAT:', number_format((float)$return->vat_amount,2)) . "\n");
            $p->selectPrintMode(Printer::MODE_EMPHASIZED);
            $p->text('Returned: ' . number_format((float)$return->line_total,2) . "\n");
            $p->selectPrintMode();
            if ($refund) {
                $p->text('Refund: ' . number_format(abs($refundAmt),2) . ($refund->payment_method ? (' ('.strtoupper($refund->payment_method).')') : '') . "\n");
            }
            $p->text("------------------------------\n");
            try { $p->qrCode($qrData); $p->feed(1); } catch (\Throwable $e) { }
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->text("Thank you!\n\n");
            $p->feed(2);
            $p->cut();
        } finally {
            $p->close();
        }
        return null;
    }

    /**
     * Print an invoice to ESC/POS printer or return dummy text buffer
     */
    public function printInvoice(SalesOrders $order, PrinterSetting $printer, bool $dummy = false): ?string
    {
        $order->loadMissing(['items','customer','creator','subshop']);
        // compute paid/remaining
        $paid = (float) \App\Models\Transaction::where('order_id', $order->id)
            ->where('transaction_type', 'payment')
            ->sum('total_amount');
        $remaining = max(0, (float)$order->grand_total - $paid);

        // QR data (could be order URL or payload)
        $qrData = $order->order_no;

        if ($dummy) {
            $lines = [];
            $lines[] = $order->subshop ? strtoupper($order->subshop->name) : 'SHOP';
            $lines[] = 'INVOICE #' . $order->order_no;
            $lines[] = now()->format('Y-m-d H:i');
            $lines[] = str_repeat('-', 32);
            if ($order->customer) {
                $lines[] = 'Customer: ' . $order->customer->name;
            }
            $lines[] = str_repeat('-', 32);
            foreach ($order->items as $it) {
                $name = $it->item_name;
                $qty = (int)$it->quantity;
                $price = number_format((float)$it->unit_price, 2);
                $total = number_format((float)$it->line_total, 2);
                $lines[] = $name;
                $lines[] = sprintf('  %dx%s  %s', $qty, $price, $total);
            }
            $lines[] = str_repeat('-', 32);
            $lines[] = sprintf('%-16s %16s', 'Subtotal:', number_format((float)$order->subtotal, 2));
            $lines[] = sprintf('%-16s %16s', 'VAT:', number_format((float)$order->vat_total, 2));
            $lines[] = sprintf('%-16s %16s', 'Discount:', number_format((float)$order->discount_total, 2));
            $lines[] = sprintf('%-16s %16s', 'Grand:', number_format((float)$order->grand_total, 2));
            $lines[] = sprintf('%-16s %16s', 'Paid:', number_format($paid, 2));
            $lines[] = sprintf('%-16s %16s', 'Remaining:', number_format($remaining, 2));
            $lines[] = str_repeat('-', 32);
            $lines[] = 'QR: ' . $qrData;
            $lines[] = 'Thank you!';
            $raw = implode("\n", $lines) . "\n\n";
            return base64_encode($raw);
        }

        $connector = new NetworkPrintConnector($printer->ip_address, (int)$printer->port, 5);
        $p = new Printer($connector);
        try {
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $p->text(($order->subshop ? strtoupper($order->subshop->name) : 'SHOP') . "\n");
            $p->selectPrintMode();
            $p->text('INVOICE #' . $order->order_no . "\n");
            $p->text(now()->format('Y-m-d H:i') . "\n");
            $p->text("------------------------------\n");
            if ($order->customer) {
                $p->setJustification(Printer::JUSTIFY_LEFT);
                $p->text('Customer: ' . $order->customer->name . "\n");
            }
            $p->text("------------------------------\n");
            foreach ($order->items as $it) {
                $p->setJustification(Printer::JUSTIFY_LEFT);
                $p->text($it->item_name . "\n");
                $line = sprintf('  %dx%s', (int)$it->quantity, number_format((float)$it->unit_price, 2));
                $p->text($line);
                $p->setJustification(Printer::JUSTIFY_RIGHT);
                $p->text('  ' . number_format((float)$it->line_total, 2) . "\n");
            }
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text("------------------------------\n");
            $p->text(sprintf('%-12s %s', 'Subtotal:', number_format((float)$order->subtotal, 2)) . "\n");
            $p->text(sprintf('%-12s %s', 'VAT:', number_format((float)$order->vat_total, 2)) . "\n");
            $p->text(sprintf('%-12s %s', 'Discount:', number_format((float)$order->discount_total, 2)) . "\n");
            $p->setJustification(Printer::JUSTIFY_RIGHT);
            $p->selectPrintMode(Printer::MODE_EMPHASIZED);
            $p->text('Grand: ' . number_format((float)$order->grand_total, 2) . "\n");
            $p->selectPrintMode();
            $p->setJustification(Printer::JUSTIFY_LEFT);
            $p->text(sprintf('%-12s %s', 'Paid:', number_format($paid, 2)) . "\n");
            $p->text(sprintf('%-12s %s', 'Remaining:', number_format($remaining, 2)) . "\n");
            $p->text("------------------------------\n");
            // QR Code
            try { $p->qrCode($qrData); $p->feed(1); } catch (\Throwable $e) { /* ignore qr errors */ }
            $p->setJustification(Printer::JUSTIFY_CENTER);
            $p->text("Thank you!\n\n");
            $p->feed(2);
            $p->cut();
        } finally {
            $p->close();
        }
        return null;
    }
}
