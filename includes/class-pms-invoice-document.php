<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Invoice PDF renderer and email delivery.
 *
 * This is intentionally dependency-free so the ERP can generate a real PDF
 * attachment on standard WordPress hosting without requiring Composer.
 */
class PMS_Invoice_Document
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;

    public static function invoice(int $invoice_id): ?object
    {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PMS_Invoicing::invoices_table() . ' WHERE id = %d',
            $invoice_id
        ));
        return $invoice ?: null;
    }

    public static function pdf_path(int $invoice_id): string
    {
        $invoice = self::invoice($invoice_id);
        if (! $invoice) {
            return '';
        }

        $upload = wp_upload_dir();
        if (! empty($upload['error'])) {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'pms-invoices';
        if (! wp_mkdir_p($dir)) {
            return '';
        }

        $path = trailingslashit($dir) . sanitize_file_name($invoice->invoice_number . '.pdf');
        $pdf = self::build_pdf($invoice, PMS_Invoicing::invoice_items($invoice_id));
        if ($pdf === '') {
            return '';
        }

        return file_put_contents($path, $pdf) !== false ? $path : '';
    }

    public static function email(int $invoice_id, string $to = ''): bool
    {
        $invoice = self::invoice($invoice_id);
        if (! $invoice) {
            return false;
        }

        $to = sanitize_email($to ?: $invoice->customer_email);
        if (! is_email($to)) {
            return false;
        }

        $path = self::pdf_path($invoice_id);
        if ($path === '' || ! file_exists($path)) {
            return false;
        }

        $subject = sprintf(
            __('Invoice %s from %s', 'pms'),
            $invoice->invoice_number,
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );
        $message = sprintf(
            __("Dear %s,\n\nPlease find attached invoice %s.\n\nAmount due: %s\nDue date: %s\n\nThank you.\n%s", 'pms'),
            $invoice->customer_name,
            $invoice->invoice_number,
            strtoupper($invoice->currency) . ' ' . number_format((float) $invoice->total - (float) $invoice->amount_paid, 2),
            $invoice->due_date ?: __('Upon receipt', 'pms'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($to, $subject, $message, $headers, [$path]);
    }

    public static function download(int $invoice_id): void
    {
        $invoice = self::invoice($invoice_id);
        if (! $invoice) {
            wp_die(__('Invoice not found.', 'pms'));
        }

        $pdf = self::build_pdf($invoice, PMS_Invoicing::invoice_items($invoice_id));
        if ($pdf === '') {
            wp_die(__('The invoice PDF could not be generated.', 'pms'));
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($invoice->invoice_number . '.pdf') . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public static function preview(int $invoice_id): void
    {
        $invoice = self::invoice($invoice_id);
        if (! $invoice) {
            wp_die(__('Invoice not found.', 'pms'));
        }

        $items = PMS_Invoicing::invoice_items($invoice_id);
        $profile = PMS_Settings::company_profile();
        $banking = PMS_Settings::banking_details();
        $company = wp_specialchars_decode((string) $profile['name'], ENT_QUOTES);
        $currency = strtoupper((string) $invoice->currency);
        $balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html($invoice->invoice_number); ?></title>
            <style>
                @page { size: A4; margin: 0; }
                * { box-sizing: border-box; }
                body { margin:0; background:#eef2f7; font-family:Arial,Helvetica,sans-serif; color:#172033; }
                .sheet { width:210mm; min-height:297mm; margin:20px auto; background:#fff; padding:28mm 18mm 20mm; box-shadow:0 8px 30px rgba(15,23,42,.12); }
                .top { display:flex; justify-content:space-between; gap:30px; border-bottom:4px solid #2563eb; padding-bottom:20px; }
                .brand { font-size:24px; font-weight:800; color:#0b1633; }
                .label { color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.12em; }
                h1 { margin:0; font-size:32px; color:#2563eb; }
                .meta { text-align:right; line-height:1.7; font-size:13px; }
                .bill { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin:28px 0; }
                .box { background:#f7f9fc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; }
                .box strong { display:block; margin-top:5px; font-size:16px; }
                table { width:100%; border-collapse:collapse; margin-top:18px; }
                th { text-align:left; background:#0b1633; color:#fff; padding:12px 10px; font-size:11px; text-transform:uppercase; letter-spacing:.05em; }
                td { padding:12px 10px; border-bottom:1px solid #e2e8f0; font-size:13px; }
                .num { text-align:right; }
                .summary { width:290px; margin:25px 0 0 auto; }
                .summary div { display:flex; justify-content:space-between; padding:7px 0; }
                .summary .total { border-top:2px solid #0b1633; margin-top:6px; padding-top:12px; font-size:19px; font-weight:800; color:#2563eb; }
                .note { margin-top:30px; padding:16px; border-left:4px solid #2563eb; background:#f7f9fc; white-space:pre-wrap; font-size:12px; line-height:1.6; }
                .footer { margin-top:40px; color:#64748b; font-size:11px; border-top:1px solid #e2e8f0; padding-top:14px; }
                @media print { body { background:#fff; } .sheet { margin:0; box-shadow:none; } .actions { display:none; } }
                .actions { position:fixed; right:20px; top:20px; display:flex; gap:8px; }
                .actions button { border:0; border-radius:7px; padding:10px 14px; background:#2563eb; color:#fff; font-weight:700; cursor:pointer; }
            </style>
        </head>
        <body>
        <div class="actions"><button onclick="window.print()">Print / Save PDF</button></div>
        <main class="sheet">
            <div class="top">
                <div style="display:flex;align-items:flex-start;gap:16px;"><?php if ($profile['logo_url']) : ?><img src="<?php echo esc_url($profile['logo_url']); ?>" alt="<?php echo esc_attr($company); ?>" style="max-width:150px;max-height:70px;object-fit:contain;"><?php endif; ?><div><div class="brand"><?php echo esc_html($company); ?></div><div class="label">Accounts & Billing</div><div style="font-size:11px;color:#64748b;line-height:1.5;margin-top:6px;"><?php echo nl2br(esc_html($profile['address'])); ?><?php if ($profile['city'] || $profile['state'] || $profile['country']) : ?><br><?php echo esc_html(implode(', ', array_filter([$profile['city'], $profile['state'], $profile['country']]))); ?><?php endif; ?><?php if ($profile['phone']) : ?><br><?php echo esc_html($profile['phone']); ?><?php endif; ?><?php if ($profile['email']) : ?><br><?php echo esc_html($profile['email']); ?><?php endif; ?><?php if ($profile['website']) : ?><br><?php echo esc_html($profile['website']); ?><?php endif; ?></div></div></div>
                <div class="meta"><h1>INVOICE</h1><strong><?php echo esc_html($invoice->invoice_number); ?></strong><br>Issue: <?php echo esc_html($invoice->issue_date); ?><br>Due: <?php echo esc_html($invoice->due_date ?: 'Upon receipt'); ?></div>
            </div>
            <div class="bill">
                <div class="box"><div class="label">Bill To</div><strong><?php echo esc_html($invoice->customer_name); ?></strong><?php if ($invoice->customer_email) : ?><div><?php echo esc_html($invoice->customer_email); ?></div><?php endif; ?></div>
                <div class="box"><div class="label">Payment Status</div><strong><?php echo esc_html(ucfirst($invoice->status)); ?></strong><div>Balance: <?php echo esc_html($currency . ' ' . number_format($balance, 2)); ?></div></div>
            </div>
            <table><thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Amount</th></tr></thead><tbody>
            <?php foreach ($items as $item) : ?><tr><td><?php echo esc_html($item['description']); ?></td><td class="num"><?php echo esc_html(number_format((float) $item['quantity'], 2)); ?></td><td class="num"><?php echo esc_html($currency . ' ' . number_format((float) $item['unit_price'], 2)); ?></td><td class="num"><?php echo esc_html($currency . ' ' . number_format((float) $item['amount'], 2)); ?></td></tr><?php endforeach; ?>
            </tbody></table>
            <div class="summary">
                <div><span>Subtotal</span><strong><?php echo esc_html($currency . ' ' . number_format((float) $invoice->subtotal, 2)); ?></strong></div>
                <div><span>VAT <?php echo ((float) $invoice->vat_rate > 0 ? esc_html(number_format((float) $invoice->vat_rate, 2) . '%') : ''); ?><?php echo ! empty($invoice->vat_inclusive) ? ' (inclusive)' : ''; ?></span><strong><?php echo esc_html($currency . ' ' . number_format((float) $invoice->tax, 2)); ?></strong></div>
                <div class="total"><span>Total</span><strong><?php echo esc_html($currency . ' ' . number_format((float) $invoice->total, 2)); ?></strong></div>
                <div><span>Amount Paid</span><strong><?php echo esc_html($currency . ' ' . number_format((float) $invoice->amount_paid, 2)); ?></strong></div>
                <div><span>Balance Due</span><strong><?php echo esc_html($currency . ' ' . number_format($balance, 2)); ?></strong></div>
            </div>
            <?php if ($banking['bank_name'] || $banking['account_name'] || $banking['account_number'] || $banking['sort_code'] || $banking['instructions']) : ?><div class="note"><strong>Payment / Bank Details</strong><br><?php if ($banking['bank_name']) : ?>Bank: <?php echo esc_html($banking['bank_name']); ?><br><?php endif; ?><?php if ($banking['account_name']) : ?>Account Name: <?php echo esc_html($banking['account_name']); ?><br><?php endif; ?><?php if ($banking['account_number']) : ?>Account Number: <?php echo esc_html($banking['account_number']); ?><br><?php endif; ?><?php if ($banking['sort_code']) : ?>Sort / Routing Code: <?php echo esc_html($banking['sort_code']); ?><br><?php endif; ?><?php if ($banking['instructions']) : ?><?php echo nl2br(esc_html($banking['instructions'])); ?><?php endif; ?></div><?php endif; ?>
            <?php if ($invoice->notes) : ?><div class="note"><strong>Notes</strong><br><?php echo esc_html($invoice->notes); ?></div><?php endif; ?>
            <div class="footer">Thank you for your business. This invoice was generated by <?php echo esc_html($company); ?>.</div>
        </main>
        </body>
        </html>
        <?php
        exit;
    }

    private static function build_pdf(object $invoice, array $items): string
    {
        $currency = strtoupper((string) $invoice->currency);
        $profile = PMS_Settings::company_profile();
        $banking = PMS_Settings::banking_details();
        $company = wp_specialchars_decode((string) $profile['name'], ENT_QUOTES);
        $balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
        $pages = [];
        $chunks = array_chunk($items, 15);
        if (! $chunks) {
            $chunks = [[]];
        }

        foreach ($chunks as $page_index => $page_items) {
            $c = '';
            self::rect($c, 0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [0.043, 0.086, 0.20], true);
            self::rect($c, 0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT - 105, [1, 1, 1], true);
            $logo = self::pdf_logo();
            if ($logo) {
                self::image($c, 42, 742, 105, 38);
            }
            self::text($c, $logo ? 160 : 42, 790, $company, 18, [1, 1, 1], true);
            self::text($c, 42, 770, 'ACCOUNTS & BILLING', 8, [0.72, 0.80, 0.92]);
            $company_line = implode(' | ', array_filter([$profile['phone'], $profile['email'], $profile['website']]));
            if ($profile['address']) { self::text($c, 42, 755, self::truncate($profile['address'], 72), 7, [0.72, 0.80, 0.92]); }
            if ($company_line) { self::text($c, 42, 742, self::truncate($company_line, 72), 7, [0.72, 0.80, 0.92]); }
            self::text($c, 405, 790, 'INVOICE', 20, [0.22, 0.52, 1], true);
            self::text($c, 405, 768, $invoice->invoice_number, 10, [1, 1, 1]);
            self::text($c, 42, 715, 'BILL TO', 8, [0.39, 0.45, 0.54], true);
            self::text($c, 42, 696, $invoice->customer_name, 13, [0.05, 0.09, 0.20], true);
            if ($invoice->customer_email) {
                self::text($c, 42, 680, $invoice->customer_email, 9, [0.39, 0.45, 0.54]);
            }
            self::text($c, 390, 715, 'ISSUE DATE', 8, [0.39, 0.45, 0.54], true);
            self::text($c, 390, 696, (string) $invoice->issue_date, 10, [0.05, 0.09, 0.20]);
            self::text($c, 480, 715, 'DUE DATE', 8, [0.39, 0.45, 0.54], true);
            self::text($c, 480, 696, (string) ($invoice->due_date ?: 'Upon receipt'), 9, [0.05, 0.09, 0.20]);

            $y = 642;
            self::rect($c, 42, $y - 17, 511, 25, [0.043, 0.086, 0.20], true);
            self::text($c, 50, $y - 9, 'DESCRIPTION', 8, [1, 1, 1], true);
            self::text($c, 370, $y - 9, 'QTY', 8, [1, 1, 1], true);
            self::text($c, 425, $y - 9, 'UNIT PRICE', 8, [1, 1, 1], true);
            self::text($c, 500, $y - 9, 'AMOUNT', 8, [1, 1, 1], true);
            $y -= 37;

            foreach ($page_items as $item) {
                self::line($c, 42, $y - 7, 553, $y - 7, [0.88, 0.90, 0.94]);
                self::text($c, 50, $y, self::truncate($item['description'], 52), 9, [0.09, 0.13, 0.20]);
                self::text($c, 370, $y, number_format((float) $item['quantity'], 2), 9, [0.09, 0.13, 0.20]);
                self::text($c, 425, $y, $currency . ' ' . number_format((float) $item['unit_price'], 2), 8, [0.09, 0.13, 0.20]);
                self::text($c, 500, $y, $currency . ' ' . number_format((float) $item['amount'], 2), 8, [0.09, 0.13, 0.20]);
                $y -= 28;
            }

            if ($page_index === count($chunks) - 1) {
                $box_y = max(185, $y - 18);
                self::rect($c, 325, $box_y - 125, 228, 125, [0.965, 0.975, 0.99], true);
                self::text($c, 340, $box_y - 22, 'SUBTOTAL', 8, [0.39, 0.45, 0.54], true);
                self::text($c, 470, $box_y - 22, $currency . ' ' . number_format((float) $invoice->subtotal, 2), 9, [0.05, 0.09, 0.20]);
                $vat_label = 'VAT ' . ((float) $invoice->vat_rate > 0 ? number_format((float) $invoice->vat_rate, 2) . '%' : '');
                if (! empty($invoice->vat_inclusive)) {
                    $vat_label .= ' INCL.';
                }
                self::text($c, 340, $box_y - 45, self::truncate($vat_label, 24), 8, [0.39, 0.45, 0.54], true);
                self::text($c, 470, $box_y - 45, $currency . ' ' . number_format((float) $invoice->tax, 2), 9, [0.05, 0.09, 0.20]);
                self::line($c, 340, $box_y - 58, 538, $box_y - 58, [0.78, 0.82, 0.89]);
                self::text($c, 340, $box_y - 82, 'TOTAL', 10, [0.15, 0.38, 0.90], true);
                self::text($c, 455, $box_y - 82, $currency . ' ' . number_format((float) $invoice->total, 2), 12, [0.15, 0.38, 0.90], true);
                self::text($c, 340, $box_y - 103, 'BALANCE DUE', 9, [0.05, 0.09, 0.20], true);
                self::text($c, 455, $box_y - 103, $currency . ' ' . number_format($balance, 2), 10, [0.05, 0.09, 0.20], true);
                if ($banking['bank_name'] || $banking['account_name'] || $banking['account_number'] || $banking['sort_code']) {
                    self::text($c, 42, 150, 'PAYMENT / BANK DETAILS', 8, [0.39, 0.45, 0.54], true);
                    $bank_line = implode(' | ', array_filter([$banking['bank_name'], $banking['account_name'], $banking['account_number'], $banking['sort_code']]));
                    self::text($c, 42, 133, self::truncate($bank_line, 88), 8, [0.20, 0.25, 0.34]);
                }
                if ($invoice->notes) {
                    self::text($c, 42, 112, 'NOTES', 8, [0.39, 0.45, 0.54], true);
                    self::text($c, 42, 95, self::truncate($invoice->notes, 88), 8, [0.20, 0.25, 0.34]);
                }
            }

            self::text($c, 42, 45, 'Generated by ' . self::truncate($company, 55), 7, [0.50, 0.56, 0.65]);
            self::text($c, 500, 45, 'Page ' . ($page_index + 1), 7, [0.50, 0.56, 0.65]);
            $pages[] = $c;
        }

        return self::assemble($pages, $logo);
    }

    private static function pdf_logo(): ?array
    {
        $id = absint(get_option('pms_company_logo_id', 0));
        if (! $id) { return null; }
        $path = get_attached_file($id);
        if (! $path || ! file_exists($path)) { return null; }
        $type = wp_check_filetype($path)['type'] ?? '';
        if ($type === 'image/jpeg' || $type === 'image/jpg') {
            $data = file_get_contents($path);
        } elseif ($type === 'image/png' && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
            $source = @imagecreatefrompng($path);
            if (! $source) { return null; }
            $width = imagesx($source); $height = imagesy($source);
            $canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagealphablending($canvas, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            ob_start(); imagejpeg($canvas, null, 90); $data = ob_get_clean();
            imagedestroy($source); imagedestroy($canvas);
        } else {
            return null;
        }
        if (! $data) { return null; }
        $size = @getimagesize($path);
        if (! $size || empty($size[0]) || empty($size[1])) { return null; }
        return ['data' => $data, 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    private static function image(string &$c, float $x, float $y, float $w, float $h): void
    {
        $c .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q\\n", $w, $h, $x, $y);
    }

    private static function assemble(array $contents, ?array $image = null): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $page_count = count($contents);
        $kids = [];
        for ($i = 0; $i < $page_count; $i++) {
            $kids[] = (3 + ($i * 2)) . ' 0 R';
        }
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $page_count . ' >>';

        foreach ($contents as $content) {
            $page_obj = count($objects) + 1;
            $content_obj = $page_obj + 1;
            $font_obj_ref = $page_count * 2 + 3;
            $image_obj_ref = $image ? $font_obj_ref + 1 : 0;
            $xobject = $image ? ' /XObject << /Im1 ' . $image_obj_ref . ' 0 R >>' : '';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $font_obj_ref . ' 0 R >>' . $xobject . ' >> /Contents ' . $content_obj . ' 0 R >>';
            $objects[] = '<< /Length ' . strlen($content) . ' >>\nstream\n' . $content . '\nendstream';
        }

        $font_obj = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        if ($image) {
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width'] . ' /Height ' . $image['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($image['data']) . ' >>\\nstream\\n' . $image['data'] . '\\nendstream';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private static function text(string &$c, float $x, float $y, string $text, int $size, array $rgb, bool $bold = false): void
    {
        $font = $bold ? 'F1' : 'F1';
        $c .= sprintf(
            "q %.3f %.3f %.3f rg BT /%s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET Q\n",
            $rgb[0], $rgb[1], $rgb[2], $font, $size, $x, $y, self::escape(self::ascii($text))
        );
    }

    private static function rect(string &$c, float $x, float $y, float $w, float $h, array $rgb, bool $fill): void
    {
        $c .= sprintf("q %.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re %s Q\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h, $fill ? 'f' : 'S');
    }

    private static function line(string &$c, float $x1, float $y1, float $x2, float $y2, array $rgb): void
    {
        $c .= sprintf("q %.3f %.3f %.3f RG 0.7 w %.2f %.2f m %.2f %.2f l S Q\n", $rgb[0], $rgb[1], $rgb[2], $x1, $y1, $x2, $y2);
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }

    private static function ascii(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : preg_replace('/[^\\x20-\\x7E]/', '', $text);
    }

    private static function truncate(string $text, int $length): string
    {
        $text = trim(preg_replace('/\\s+/', ' ', $text));
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}
