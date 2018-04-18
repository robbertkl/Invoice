<?php
namespace Administration;

use Endroid\QrCode\QrCode;
use SimplePdf\Page;
use ZendPdf\Font;
use ZendPdf\Image;
use ZendPdf\PdfDocument as Pdf;

class Invoice
{
    const DEFAULT_FONT_SIZE = 12;
    const DEFAULT_LINE_SPACING = 1.2;
    const DEFAULT_LINE_WIDTH = 0.25;

    const DEFAULT_VAT_PERCENTAGE = 0.21;
    const DEFAULT_PAYMENT_DEADLINE = 14;

    public $fontSize = self::DEFAULT_FONT_SIZE;
    public $lineSpacing = self::DEFAULT_LINE_SPACING;
    public $lineWidth = self::DEFAULT_LINE_WIDTH;

    public $vatPercentage = self::DEFAULT_VAT_PERCENTAGE;
    public $paymentDeadline = self::DEFAULT_PAYMENT_DEADLINE;

    public static $defaultCompanyName = '';
    public static $defaultCompanyInfo = '';
    public static $defaultKvkNumber = '';
    public static $defaultVatNumber = '';
    public static $defaultBankName = '';
    public static $defaultIban = '';
    public static $defaultBic = '';
    public static $defaultNotification = '';

    public $companyName;
    public $companyInfo;
    public $kvkNumber;
    public $vatNumber;
    public $bankName;
    public $iban;
    public $bic;
    public $notification;

    protected $invoiceNumber;
    protected $invoiceDate;
    protected $recipient;

    protected $items = [];

    public function __construct($invoiceNumber, $invoiceDate, $recipient)
    {
        $this->invoiceNumber = $invoiceNumber;
        $this->invoiceDate = $invoiceDate;
        $this->recipient = $recipient;

        $this->companyName = self::$defaultCompanyName;
        $this->companyInfo = self::$defaultCompanyInfo;
        $this->kvkNumber = self::$defaultKvkNumber;
        $this->vatNumber = self::$defaultVatNumber;
        $this->bankName = self::$defaultBankName;
        $this->iban = self::$defaultIban;
        $this->bic = self::$defaultBic;
        $this->notification = self::$defaultNotification;
    }

    public function addItem($description, $price)
    {
        if (isset($this->items[$description])) {
            throw new \RuntimeException('Item already exists');
        }
        $this->items[$description] = $price;
    }

    public function exportToPdf()
    {
        $page = new Page(Page::SIZE_A4, Page::UNITS_CENTIMETER);
        $page->setFont(Font::fontWithName(Font::FONT_HELVETICA), 12);
        $page->setMargins(3, 3, 2, 2);
        $page->setFontSize($this->fontSize);
        $page->setLineSpacing($this->lineSpacing);
        $page->setLineWidth($this->lineWidth);

        $y = 0;
        $y = $this->insertSenderInfo($page, $y);
        $y += 2 * $page->getLineHeight();
        $y = $this->insertRecipientInfo($page, $y);
        $y += 2 * $page->getLineHeight();
        $y = $this->insertInfoTable($page, $y);
        $y += 2 * $page->getLineHeight();
        $y = $this->insertItemTable($page, $y);
        if (!empty(trim($this->notification))) {
            $y += 2 * $page->getLineHeight();
            $y = $this->insertNotification($page, $y);
        }

        $this->insertFooter($page);

        $tempFile = tempnam('/tmp', 'qr') . '.png';
        file_put_contents($tempFile, $this->getQrCode());
        $qr = Image::imageWithPath($tempFile);
        $page->drawImage($qr, 0, 0, 3.6, 3.6);
        unlink($tempFile);

        $pdf = new Pdf();
        $pdf->pages[] = $page;
        return $pdf;
    }

    public function getQrCode()
    {
        $data = [
            'BCD',
            '002',
            '1',
            'SCT',
            '',
            $this->companyName,
            preg_replace('/[^0-9A-Z]/', '', $this->iban),
            'EUR' . number_format($this->getTotalWithVat(), 2, '.', ''),
            '',
            '',
            $this->invoiceNumber,
            '',
        ];

        $qr = new QrCode();
        $qr->setText(implode(PHP_EOL, $data));
        $qr->setSize(600);
        $qr->setPadding(0);
        $qr->setErrorCorrection('medium');

        return $qr->get('png');
    }

    public function getTotalWithVat()
    {
        $totalWithoutVat = 0;
        foreach ($this->items as $title => $amount) {
            $totalWithoutVat += round($amount, 2);
        }
        $totalVat = round($totalWithoutVat * $this->vatPercentage, 2);
        $totalWithVat = round($totalWithoutVat + $totalVat, 2);
        return $totalWithVat;
    }

    protected function insertSenderInfo(Page $page, $y)
    {
        $companyInfo = trim($this->companyName . PHP_EOL . $this->companyInfo, PHP_EOL);
        $companyInfoWidth = 0;
        foreach (explode(PHP_EOL, $companyInfo) as $line) {
            $companyInfoWidth = max($companyInfoWidth, $page->getTextWidth($line));
        }
        $page->drawTextBlock($companyInfo, $y, Page::TEXT_ALIGN_LEFT, $page->getInnerWidth() - $companyInfoWidth);
        return $y + (substr_count($companyInfo, PHP_EOL) + 1) * $page->getLineHeight();
    }

    protected function insertRecipientInfo(Page $page, $y)
    {
        $recipient = trim($this->recipient, PHP_EOL);
        $page->drawTextBlock($recipient, $y);
        return $y + (substr_count($recipient, PHP_EOL) + 1) * $page->getLineHeight();
    }

    protected function insertInfoTable(Page $page, $y)
    {
        $table = [
            [
                'Factuurnummer' => $this->invoiceNumber,
                'Factuurdatum' => $this->invoiceDate,
            ],
            [
                'KvK nummer' => $this->kvkNumber,
                'BTW nummer' => $this->vatNumber,
            ],
            [
                'IBAN' => $this->iban,
                'BIC' => $this->bic,
            ],
        ];

        $keyFontSize = $this->fontSize - 2;
        $keyLineSpacing = 1.0;
        $valueFontSize = $this->fontSize;
        $valueLineSpacing = 1.3;
        $tableCellMargin = 0.2;

        $columnWidths = [];
        $maxHeight = 0;
        foreach ($table as $column => $rows) {
            $height = 0;
            $columnWidths[$column] = 0;
            foreach ($rows as $key => $value) {
                $page->setFontSize($keyFontSize);
                $page->setLineSpacing($keyLineSpacing);
                $columnWidths[$column] = max($columnWidths[$column], $page->getTextWidth($key));
                $height += $page->getLineHeight();

                $page->setFontSize($valueFontSize);
                $page->setLineSpacing($valueLineSpacing);
                $columnWidths[$column] = max($columnWidths[$column], $page->getTextWidth($value));
                $height += $page->getLineHeight();
            }
            $maxHeight = max($maxHeight, $height);
        }

        $totalWidth = array_sum($columnWidths);
        foreach ($columnWidths as &$columnWidth) {
            $columnWidth = $page->getInnerWidth() * $columnWidth / $totalWidth;
        }

        $left = 0;
        $top = $y;
        $page->drawLine($left, $top, $left, $top + $maxHeight);

        foreach ($table as $column => $rows) {
            foreach ($rows as $key => $value) {
                $page->setFontSize($keyFontSize);
                $page->setLineSpacing($keyLineSpacing);
                $page->drawText($key, $left + $tableCellMargin, $top + 0.1);
                $top += $page->getLineHeight();

                $page->setFontSize($valueFontSize);
                $page->setLineSpacing($valueLineSpacing);
                $page->drawText($value, $left + $tableCellMargin, $top + 0.1);
                $top += $page->getLineHeight();
            }

            $left += $columnWidths[$column];
            $top = $y;
            $page->drawLine($left, $top, $left, $top + $maxHeight);
        }

        $page->setFontSize($this->fontSize);
        $page->setLineSpacing($this->lineSpacing);

        return $y + $maxHeight;
    }

    protected function insertItemTable(Page $page, $y)
    {
        $page->setLineSpacing($page->getLineSpacing() + 0.4);
        $spaceBeforeLine = -0.1;
        $spaceAfterLine = 0.18;

        $page->drawLine(0, $y, $page->getInnerWidth(), $y);
        $y += $spaceAfterLine;
        $oldFont = $page->getFont();
        $page->setFont(Font::fontWithName(Font::FONT_HELVETICA_BOLD));
        $page->drawTextBlock('Omschrijving', $y, Page::TEXT_ALIGN_LEFT);
        $page->drawTextBlock('Bedrag', $y, Page::TEXT_ALIGN_RIGHT);
        $page->setFont($oldFont);
        $y += $page->getLineHeight();
        $y += $spaceBeforeLine;
        $page->drawLine(0, $y, $page->getInnerWidth(), $y);
        $y += $spaceAfterLine;

        $totalWithoutVat = 0;
        foreach ($this->items as $title => $amount) {
            $amount = round($amount, 2);
            $page->drawTextBlock($title, $y, Page::TEXT_ALIGN_LEFT);
            $page->drawTextBlock($this->formatAmount($amount), $y, Page::TEXT_ALIGN_RIGHT);
            $y += $page->getLineHeight();
            $totalWithoutVat += $amount;
        }

        $y += $page->getLineHeight();

        $y += $spaceBeforeLine;
        $page->drawLine($page->getInnerWidth() - 3, $y, $page->getInnerWidth(), $y);
        $y += $spaceAfterLine;

        $totalWithoutVat = round($totalWithoutVat, 2);
        $page->drawTextBlock('Subtotaal', $y, Page::TEXT_ALIGN_LEFT, $page->getInnerWidth() - 6);
        $page->drawTextBlock($this->formatAmount($totalWithoutVat), $y, Page::TEXT_ALIGN_RIGHT);
        $y += $page->getLineHeight();

        $totalVat = round($totalWithoutVat * $this->vatPercentage, 2);
        $vatLabel = 'BTW ' . $this->formatPercentage($this->vatPercentage);
        $page->drawTextBlock($vatLabel, $y, Page::TEXT_ALIGN_LEFT, $page->getInnerWidth() - 6);
        $page->drawTextBlock($this->formatAmount($totalVat), $y, Page::TEXT_ALIGN_RIGHT);
        $y += $page->getLineHeight();

        $y += $spaceBeforeLine;
        $page->drawLine($page->getInnerWidth() - 3, $y, $page->getInnerWidth(), $y);
        $y += $spaceAfterLine;

        $totalWithVat = round($totalWithoutVat + $totalVat, 2);
        $oldFont = $page->getFont();
        $page->setFont(Font::fontWithName(Font::FONT_HELVETICA_BOLD));
        $page->drawTextBlock('Totaal', $y, Page::TEXT_ALIGN_LEFT, $page->getInnerWidth() - 6);
        $page->drawTextBlock($this->formatAmount($totalWithVat), $y, Page::TEXT_ALIGN_RIGHT);
        $page->setFont($oldFont);
        $y += $page->getLineHeight();

        $y += $spaceBeforeLine;
        $page->drawLine($page->getInnerWidth() - 3, $y, $page->getInnerWidth(), $y);
        $y += $spaceAfterLine;

        $page->setLineSpacing($this->lineSpacing);
        return $y;
    }

    protected function insertNotification(Page $page, $y)
    {
        $notification = trim($this->notification, PHP_EOL);
        $page->drawTextBlock($notification, $y);
        return $y + (substr_count($notification, PHP_EOL) + 1) * $page->getLineHeight();
    }

    protected function insertFooter(Page $page)
    {
        $shortName = str_replace('HOLDING', 'HLD', str_replace('B.V.', 'BV', strtoupper($this->companyName)));
        $footerText  = 'Gelieve bovenstaand bedrag binnen ' . $this->paymentDeadline . ' dagen te voldoen';
        $footerText .= ' op rekeningnummer ' . $this->iban . ' ten name van ' . $shortName;
        $footerText .= ' onder vermelding van factuurnummer ' . $this->invoiceNumber . '.';

        $footerText = $page->wordWrapText($footerText, $page->getInnerWidth());
        $footerHeight = (substr_count($footerText, PHP_EOL) + 1) * $page->getLineHeight();
        $page->drawTextBlock($footerText, $page->getInnerHeight() - $footerHeight);

        $linePosition = $page->getInnerHeight() - $footerHeight - 0.2;
        $page->drawLine(0, $linePosition, $page->getInnerWidth(), $linePosition);
    }

    protected function formatAmount($amount)
    {
        return chr(128) . '  ' . number_format($amount, 2, ',', '.');
    }

    protected function formatPercentage($percentage)
    {
        return str_replace('.', ',', $percentage * 100) . '%';
    }
}
