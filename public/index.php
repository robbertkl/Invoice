<?php

require(__DIR__ . '/../vendor/autoload.php');

use Administration\Invoice;

Invoice::$defaultCompanyName   = getenv('INVOICE_COMPANY_NAME');
Invoice::$defaultCompanyInfo   = getenv('INVOICE_COMPANY_INFO');
Invoice::$defaultKvkNumber     = getenv('INVOICE_COMPANY_KVK');
Invoice::$defaultVatNumber     = getenv('INVOICE_COMPANY_VAT');
Invoice::$defaultBankName      = getenv('INVOICE_COMPANY_BANK');
Invoice::$defaultIban          = getenv('INVOICE_COMPANY_IBAN');
Invoice::$defaultBic           = getenv('INVOICE_COMPANY_BIC');
Invoice::$defaultNotification  = getenv('INVOICE_NOTIFICATION');

$defaultRecipient              = getenv('INVOICE_DEFAULT_RECIPIENT');

$numberOfInputRows             = getenv('INVOICE_ROWS');
if (!$numberOfInputRows) $numberOfInputRows = 6;

$numberOfLeadingZeros          = getenv('INVOICE_LEADING_ZEROS');
if (!$numberOfLeadingZeros) $numberOfLeadingZeros = 0;

$directory = __DIR__ . '/../invoices';

$invoices = [];
$invoiceCounters = [date('Y') => 0];
$iterator = new \DirectoryIterator($directory);
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/^(F(\d{4})-(\d+))\.pdf$/i', $fileInfo->getFilename(), $matches)) {
        $filename = $matches[0];
        $invoiceNumber = $matches[1];
        $year = intval($matches[2]);
        $counter = intval($matches[3]);

        $invoices[$invoiceNumber] = $filename;
        if (!isset($invoiceCounters[$year])) {
            $invoiceCounters[$year] = 0;
        }

        $invoiceCounters[$year] = max($invoiceCounters[$year], $counter);
    }
}

ksort($invoices, SORT_NATURAL);
end($invoices);
$lastInvoice = key($invoices);
reset($invoices);

if (isset($_GET['delete'])) {
    $invoiceNumber = $_GET['delete'];
    $fileName = $invoiceNumber . '.pdf';
    $filePath = $directory . '/' . $fileName;

    if (!file_exists($filePath)) {
        echo "File {$fileName} does not exist\n";
        exit;
    }

    if ($invoiceNumber != $lastInvoice) {
        echo "Invoice {$invoiceNumber} is not the last invoice\n";
        exit;
    }

    unlink($filePath);

    header('Location: /');
    exit;
}

if (isset($_GET['download'])) {
    $invoiceNumber = $_GET['download'];
    $fileName = $invoiceNumber . '.pdf';
    $filePath = $directory . '/' . $fileName;

    if (!file_exists($filePath)) {
        echo "File {$fileName} does not exist\n";
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    readfile($filePath);
    exit;
}

if (isset($_POST['submit'])) {
    $invoiceNumber = $_POST['invoiceNumber'];
    $invoiceDate = $_POST['invoiceDate'];
    $recipient = trim($_POST['recipient']);

    $invoice = new Invoice($invoiceNumber, $invoiceDate, $recipient);

    $items = [];
    for ($i = 0; $i < $numberOfInputRows; $i++) {
        if (!empty($_POST['description'][$i]) || !empty($_POST['amount'][$i])) {
            $description = $_POST['description'][$i];
            $amount = $_POST['amount'][$i];

            if (preg_match('/^[0-9.]+\*[0-9.]+$/', $amount)) {
                $amount = eval('return ' . $amount . ';');
            }

            if (preg_match('/\..*,/', $amount)) {
                $amount = str_replace('.', '', $amount);
            } elseif (preg_match('/,.*\./', $amount)) {
                $amount = str_replace(',', '', $amount);
            } elseif (preg_match('/(\.|,)\d\d\d$/', $amount)) {
                $amount = str_replace(['.', ','], '', $amount);
            }
            $amount = str_replace(',', '.', $amount);
            $amount = (float)$amount;

            $invoice->addItem($description, $amount);
        }
    }

    $fileName = $invoiceNumber . '.pdf';
    $filePath = $directory . '/' . $fileName;

    if (file_exists($filePath)) {
        echo "File '{$fileName}' already exists\n";
        exit;
    }

    $pdf = $invoice->exportToPdf();
    $pdf->save($filePath);

    header('Location: /');
    exit;
}

$nextInvoiceNumber = 'F' . date('Y') . '-' . str_pad($invoiceCounters[date('Y')] + 1, $numberOfLeadingZeros + 1, '0', STR_PAD_LEFT);;
$nextInvoiceDate = date('d-m-Y');

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Invoices</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
        <style>
            .glyphicon {
                width: 14px;
                height: 14px;
            }
        </style>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
    </head>
    <body>
        <nav class="navbar navbar-default navbar-static-top">
            <div class="container">
                <div class="navbar-header">
                    <a class="navbar-brand" href="/">Invoice generator</a>
                </div>
            </div>
        </nav>

        <div class="container">

            <form name="form" action="/" method="POST">
                <div class="form-group">
                    <label for="invoiceNumber">Invoice number</label>
                    <input type="text" class="form-control" readonly="readonly" value="<?= $nextInvoiceNumber ?>" name="invoiceNumber" id="invoiceNumber">
                </div>
                <div class="form-group">
                    <label for="invoiceDate">Invoice date</label>
                    <input type="text" class="form-control" readonly="readonly" value="<?= $nextInvoiceDate ?>" name="invoiceDate" id="invoiceDate">
                </div>
                <div class="form-group">
                    <label for="recipient">Recipient</label>
                    <textarea class="form-control" placeholder="Recipient name + address" name="recipient" id="recipient" rows="3"><?= trim($defaultRecipient) ?></textarea>
                </div>
                <label>Items</label>
<?php for ($i = 0; $i < $numberOfInputRows; $i++): ?>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-9">
                            <input type="text" class="form-control" value="" placeholder="Item description" name="description[<?= $i ?>]">
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" value="" placeholder="€ 0,00" name="amount[<?= $i ?>]" style="text-align: right">
                        </div>
                    </div>
                </div>
<?php endfor; ?>
                <input type="submit" class="btn btn-primary" value="Generate invoice" name="submit">
            </form>

            <hr>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Invoices</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach (array_reverse($invoices) as $invoiceNumber => $filename): ?>
                    <tr>
                        <td style="vertical-align: middle">
                            <span class="glyphicon glyphicon-file" aria-hidden="true"></span>
                            <?= $invoiceNumber ?>
                        </td>
                        <td class="col-md-2 text-right text-nowrap">
<?php if ($invoiceNumber == $lastInvoice): ?>
                            <a href="/?delete=<?= urlencode($invoiceNumber) ?>" class="btn btn-default btn-xs btn-danger">
                                <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Delete
                            </a>
<?php endif; ?>
                            <a href="?download=<?= urlencode($invoiceNumber) ?>" class="btn btn-default btn-xs btn-success" style="margin-left: 5px">
                                <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span> Download
                            </a>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </body>
</html>