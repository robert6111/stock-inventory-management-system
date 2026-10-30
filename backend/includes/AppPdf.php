<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class AppPdf extends Fpdi
{
    protected $letterheadTemplateId;
    protected $contentStartY = 72; // Increase to 78 or 85 if your logo/header is tall

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');

        $letterheadPath = __DIR__ . '/letterhead/letterhead.pdf';

        if (!file_exists($letterheadPath)) {
            die('Letterhead file not found: ' . $letterheadPath);
        }

        $this->setSourceFile($letterheadPath);
        $this->letterheadTemplateId = $this->importPage(1);
        $this->AliasNbPages();

        // Left, top, right margins
        $this->SetMargins(15, $this->contentStartY, 15);

        // Bottom margin
        $this->SetAutoPageBreak(true, 25);
    }

    public function Header()
    {
        // Put the letterhead PDF as the background
        $this->useTemplate($this->letterheadTemplateId, 0, 0, 210, 297);

        // Reset PDF drawing/text settings after importing the letterhead.
        // This keeps invoices and reports dark enough when printed on paper.
        $this->ApplyReadablePrintStyle();

        // Force all page content to start below the logo/header
        $this->SetY($this->contentStartY);
    }

    public function Footer()
    {
        $this->ApplyReadablePrintStyle();
        $this->SetY(-18);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function ApplyReadablePrintStyle()
    {
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetLineWidth(0.25);
    }

    public function NeedsPageBreak($height)
    {
        return ($this->GetY() + $height > $this->PageBreakTrigger);
    }
}