<?php
namespace Alphagov\GovWifi;

use Exception;
use FPDF;

class PDF {
    public $filename;
    public $filepath;
    public $subject;
    public $message;
    public $landscape;
    public $password;
    public $encrypt = true;

    public function populateNewSite($site) {
        $config = Config::getInstance();
        $this->message = file_get_contents(
                $config->values['pdf-contents']['newsite-file']);
        $this->message = str_replace("%ORG%", $site->org_name, $this->message);
        $this->message = str_replace("%RADKEY%", $site->radKey, $this->message);
        $this->message = str_replace(
                "%DESCRIPTION%", $site->name, $this->message);
        $radiusIpList = explode(",", str_replace("/32", "", $config->values['radiusIPs']));

        // TODO: think about further splitting this by region.
        shuffle($radiusIpList);
        $this->message = str_replace(
            "%RADIUS_IP_LIST%",
            implode("\n", $radiusIpList),
            $this->message);
        $radiusServerList = "";

        for ($i = 1; $i <= $config->values['radiusServerCount']; $i++) {
            $radiusServerList .= str_replace(
                "*n*", $i, $config->values['radiusHostnameTemplate']) . "\n";
        }

        $this->message = str_replace(
            "%RADIUS_SERVER_LIST%", $radiusServerList, $this->message);
        $this->filename = $site->org_name . "-" . $site->name;
        $this->filename = preg_replace("/[^a-zA-Z0-9]/", "_", $this->filename);
        $this->filename .= ".pdf";
        $this->filepath = $config->values['pdftemp-path'] . $this->filename;
        $this->subject = "New Site";
    }

    public function populateLogRequest(OrgAdmin $org_admin) {
        $config = Config::getInstance();
        $this->filename = date("Ymd") .
                $org_admin->orgName . "-" . $org_admin->name . "-Logs";
        $this->filename = preg_replace("/[^a-zA-Z0-9]/", "_", $this->filename);
        $this->filename .= ".pdf";
        $this->filepath = $config->values['pdftemp-path'] . $this->filename;
        $this->subject =
                "Generated on: " . date("d-m-Y") .
                " Requestor: " . $org_admin->name;
        $this->message = file_get_contents(
                $config->values['pdf-contents']['logrequest-file']);
    }

    public function generatePDF(Report $report = null) {
        // Generate PDF with the site details
        // Encrypts the file then returns the password
        $un_filename = $this->filepath . "-unencrypted";

        if ($this->landscape) {
            $pdf = new FPDF("L");
        } else {
            $pdf = new FPDF();
        }

        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 16);
        $pdf->Cell(40, 10, 'GovWifi Service');
        $pdf->Ln(20);
        $pdf->Cell(80, 10, $this->subject);
        $pdf->Ln(20);
        $pdf->SetFont('Arial', '', 12);
        // Write Body

        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->message) as $line) {
            if ($line == "%TABLE%") {
                $this->PdfSqlTable($pdf, $report);
            } else {
                $pdf->Write(5, $line . "\n");
            }
        }

        $pdf->Output("F", $un_filename);
        if ($this->encrypt) {
            $this->encryptPdf($un_filename);
        } else {
            $this->dontEncryptPdf($un_filename);
        }
    }

    private function dontEncryptPdf($filename) {
        copy($filename,$this->filepath);
        unlink($filename);
    }

    private function encryptPdf($filename) {
        $this->setRandomPdfPassword();
        exec("/usr/bin/qpdf --encrypt " . $this->password .
            " - 256 -- " . $filename .
            " " . $this->filepath);
        unlink($filename);
    }

    private function PdfSqlTable(FPDF $pdf, Report $report) {
        $totalrows = 0;
        $w = array_fill(0, 13, 0);
        
        // Set column width fiddle factor multiplier
        $widthConstant = 8;
        $widthMultiplier = 2.7;

        // Get column widths for headings

        $column = 0;
        while (isset($report->columns[$column])) {
            $collength = $widthConstant +
                round(($widthMultiplier * strlen($report->columns[$column])));
            if ($w[$column] < $collength) {
                $w[$column] = $collength;
            }
            $column++;
        }

        // Get column widths for data
        foreach ($report->result as $row[$totalrows]) {
            $column = 0;

            while (isset($row[$totalrows][$column])) {
                $collength = $widthConstant +
                    round(
                        ($widthMultiplier * strlen($row[$totalrows][$column])));
                if ($w[$column] < $collength) {
                    $w[$column] = $collength;
                }
                $column++;
            }
            $totalrows++;
        }

        // Write column headers
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Write(5, $report->subject . "\n");

        $column = 0;
        while (isset($report->columns[$column])) {
            $pdf->Cell($w[$column], 6, $report->columns[$column], 1, 0, 'C');
            $column++;
        }

        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);

        // Write column
        for ($rownum = 0; $rownum <= $totalrows; $rownum++) {
            $column = 0;
            while (isset($row[$rownum][$column])) {
                $pdf->Cell($w[$column], 6, $row[$rownum][$column], 1, 0, 'C');
                $column++;
            }
            $pdf->Ln();
        }
    }

    private function setRandomPdfPassword() {
        $config = Config::getInstance();
        $length = $config->values['pdf-password']['length'];
        $pattern = $config->values['pdf-password']['regex'];
        $pass = preg_replace($pattern, "", base64_encode($this->strongRandomBytes($length *
            4)));
        $this->password = substr($pass, 0, $length);
    }

    private function strongRandomBytes($length) {
        $strong = false; // Flag for whether a strong algorithm was used
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if (! $strong) {
            // System did not use a cryptographically strong algorithm
            throw new Exception('Strong algorithm not available for PRNG.');
        }
        return $bytes;
    }
}
