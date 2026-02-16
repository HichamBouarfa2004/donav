<?php
require_once(__DIR__ . '/tcpdf/tcpdf.php');

class CertificateGenerator
{
    private string $student_name, $group;
    private string $teacher_name;
    private string $activity_title;

    // OFPPT official colors
    private const OFPPT_DARK   = [0, 47, 108];    // Deep navy blue
    private const OFPPT_MEDIUM = [0, 91, 148];     // Medium blue
    private const OFPPT_LIGHT  = [0, 131, 185];    // Lighter accent blue
    private const GOLD_PRIMARY = [185, 155, 75];    // Elegant gold
    private const GOLD_DARK    = [160, 130, 55];    // Darker gold
    private const TEXT_DARK    = [40, 40, 45];      // Near-black text
    private const TEXT_MID     = [90, 90, 100];     // Secondary text
    private const BG_CREAM     = [252, 250, 245];   // Warm white background

    public function __construct(string $student_name, string $group, string $teacher_name = "Le Formateur", string $activity_title = "Soft Skills Activity")
    {
        $this->student_name = $student_name;
        $this->group = $group;
        $this->teacher_name = $teacher_name;
        $this->activity_title = $activity_title;
    }

    /**
     * Helper: set draw color from constant array
     */
    private function setDraw(TCPDF $pdf, array $c): void { $pdf->SetDrawColor($c[0], $c[1], $c[2]); }
    private function setText(TCPDF $pdf, array $c): void { $pdf->SetTextColor($c[0], $c[1], $c[2]); }
    private function setFill(TCPDF $pdf, array $c): void { $pdf->SetFillColor($c[0], $c[1], $c[2]); }

    /**
     * Prepare the OFPPT logo (convert PNG to JPG if needed for compatibility)
     */
    private function getLogoPath(): ?string
    {
        $logoPathPng = __DIR__ . '/../assets/ofppt_logo.png';
        $logoPathJpg = __DIR__ . '/../assets/ofppt_logo.jpg';

        if (file_exists($logoPathPng) && !file_exists($logoPathJpg)) {
            if (function_exists('imagecreatefrompng')) {
                $img = @imagecreatefrompng($logoPathPng);
                if ($img) {
                    $w = imagesx($img);
                    $h = imagesy($img);
                    $white = imagecreatetruecolor($w, $h);
                    imagefill($white, 0, 0, imagecolorallocate($white, 255, 255, 255));
                    imagecopy($white, $img, 0, 0, 0, 0, $w, $h);
                    imagejpeg($white, $logoPathJpg, 95);
                    imagedestroy($img);
                    imagedestroy($white);
                }
            }
        }

        if (file_exists($logoPathJpg)) return $logoPathJpg;
        if (file_exists($logoPathPng)) return $logoPathPng;
        return null;
    }

    /**
     * Draw ornamental corner brackets
     */
    private function drawCornerOrnaments(TCPDF $pdf, float $x1, float $y1, float $x2, float $y2, float $size = 18): void
    {
        $this->setDraw($pdf, self::GOLD_PRIMARY);
        $pdf->SetLineWidth(1.2);

        // Top-left
        $pdf->Line($x1, $y1, $x1 + $size, $y1);
        $pdf->Line($x1, $y1, $x1, $y1 + $size);
        // Top-right
        $pdf->Line($x2, $y1, $x2 - $size, $y1);
        $pdf->Line($x2, $y1, $x2, $y1 + $size);
        // Bottom-left
        $pdf->Line($x1, $y2, $x1 + $size, $y2);
        $pdf->Line($x1, $y2, $x1, $y2 - $size);
        // Bottom-right
        $pdf->Line($x2, $y2, $x2 - $size, $y2);
        $pdf->Line($x2, $y2, $x2, $y2 - $size);

        // Inner diamond accents at each corner
        $pdf->SetLineWidth(0.4);
        $this->setDraw($pdf, self::GOLD_DARK);
        $d = 4;
        // Top-left diamond
        $this->drawDiamond($pdf, $x1 + 1, $y1 + 1, $d);
        // Top-right diamond
        $this->drawDiamond($pdf, $x2 - 1, $y1 + 1, $d);
        // Bottom-left diamond
        $this->drawDiamond($pdf, $x1 + 1, $y2 - 1, $d);
        // Bottom-right diamond
        $this->drawDiamond($pdf, $x2 - 1, $y2 - 1, $d);
    }

    /**
     * Draw a small diamond shape at a point
     */
    private function drawDiamond(TCPDF $pdf, float $cx, float $cy, float $size): void
    {
        $half = $size / 2;
        $pdf->Line($cx, $cy - $half, $cx + $half, $cy);
        $pdf->Line($cx + $half, $cy, $cx, $cy + $half);
        $pdf->Line($cx, $cy + $half, $cx - $half, $cy);
        $pdf->Line($cx - $half, $cy, $cx, $cy - $half);
    }

    /**
     * Draw a horizontal gold separator line with small diamond in center
     */
    private function drawGoldSeparator(TCPDF $pdf, float $y, float $leftX, float $rightX): void
    {
        $midX = ($leftX + $rightX) / 2;
        $gap = 6;

        $this->setDraw($pdf, self::GOLD_PRIMARY);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($leftX, $y, $midX - $gap, $y);
        $pdf->Line($midX + $gap, $y, $rightX, $y);

        // Center diamond
        $pdf->SetLineWidth(0.5);
        $this->drawDiamond($pdf, $midX, $y, 5);
    }

    /**
     * Generate unique certificate reference number
     */
    private function generateRefNumber(): string
    {
        $year = date('Y');
        $hash = strtoupper(substr(md5($this->student_name . $this->group . $this->activity_title . $year), 0, 6));
        return "OFPPT-PIE-{$year}-{$hash}";
    }

    /**
     * Build the PDF object (shared by download and file-save methods)
     */
    private function buildPdf(): TCPDF
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OFPPT - Programme PIE');
        $pdf->SetAuthor('OFPPT');
        $pdf->SetTitle('Certificat de Participation - PIE');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $W = 297; // page width
        $H = 210; // page height

        // =====================================================================
        //  BACKGROUND — subtle warm white fill
        // =====================================================================
        $this->setFill($pdf, self::BG_CREAM);
        $pdf->Rect(0, 0, $W, $H, 'F');

        // =====================================================================
        //  BORDERS — triple-line frame (navy → gold → navy)
        // =====================================================================
        // Outermost navy border
        $this->setDraw($pdf, self::OFPPT_DARK);
        $pdf->SetLineWidth(2.5);
        $pdf->Rect(6, 6, $W - 12, $H - 12);

        // Gold middle border
        $this->setDraw($pdf, self::GOLD_PRIMARY);
        $pdf->SetLineWidth(0.8);
        $pdf->Rect(10, 10, $W - 20, $H - 20);

        // Inner navy border
        $this->setDraw($pdf, self::OFPPT_DARK);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(12, 12, $W - 24, $H - 24);

        // =====================================================================
        //  CORNER ORNAMENTS
        // =====================================================================
        $this->drawCornerOrnaments($pdf, 10, 10, $W - 10, $H - 10, 22);

        // =====================================================================
        //  TOP HEADER BAR — dark navy band
        // =====================================================================
        $this->setFill($pdf, self::OFPPT_DARK);
        $pdf->Rect(12.2, 12.2, $W - 24.4, 1.5, 'F');

        // =====================================================================
        //  OFPPT LOGO — left side
        // =====================================================================
        $logoPath = $this->getLogoPath();
        if ($logoPath) {
            try {
                $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                // Force JPG to avoid PNG alpha-channel issues with TCPDF
                if ($ext === 'png') {
                    // Try to convert on the fly to avoid rendering errors
                    $jpgFallback = __DIR__ . '/../assets/ofppt_logo_cert.jpg';
                    if (!file_exists($jpgFallback) && function_exists('imagecreatefrompng')) {
                        $img = @imagecreatefrompng($logoPath);
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $white = imagecreatetruecolor($w, $h);
                            $bg = imagecolorallocate($white, 252, 250, 245);
                            imagefill($white, 0, 0, $bg);
                            imagecopy($white, $img, 0, 0, 0, 0, $w, $h);
                            imagejpeg($white, $jpgFallback, 95);
                            imagedestroy($img);
                            imagedestroy($white);
                        }
                    }
                    if (file_exists($jpgFallback)) {
                        $logoPath = $jpgFallback;
                        $ext = 'jpg';
                    }
                }
                $pdf->Image($logoPath, 20, 18, 30, 30, strtoupper($ext));
            } catch (Exception $e) {
                // Skip logo on error — certificate still generates cleanly
            }
        }

        // =====================================================================
        //  HEADER TEXT — Kingdom / OFPPT / PIE
        // =====================================================================
        // Right-aligned "Royaume du Maroc" block
        $pdf->SetXY($W - 120, 18);
        $pdf->SetFont('helvetica', 'B', 10);
        $this->setText($pdf, self::OFPPT_DARK);
        $pdf->Cell(100, 5, "ROYAUME DU MAROC", 0, 1, 'R');

        $pdf->SetXY($W - 120, 23);
        $pdf->SetFont('helvetica', '', 8.5);
        $this->setText($pdf, self::OFPPT_MEDIUM);
        $pdf->Cell(100, 4.5, "Office de la Formation Professionnelle", 0, 1, 'R');

        $pdf->SetXY($W - 120, 27.5);
        $pdf->Cell(100, 4.5, "et de la Promotion du Travail", 0, 1, 'R');

        // Center "OFPPT" label
        $pdf->SetXY(0, 20);
        $pdf->SetFont('helvetica', 'B', 14);
        $this->setText($pdf, self::OFPPT_DARK);
        $pdf->Cell($W, 6, "OFPPT", 0, 1, 'C');

        // PIE subtitle
        $pdf->SetXY(0, 27);
        $pdf->SetFont('helvetica', '', 9);
        $this->setText($pdf, self::OFPPT_MEDIUM);
        $pdf->Cell($W, 5, "Programme d'Innovation Entrepreneuriale", 0, 1, 'C');
        $pdf->SetXY(0, 32);
        $pdf->Cell($W, 4, "PIE - Soft Skills & Employabilité", 0, 1, 'C');

        // =====================================================================
        //  TOP GOLD SEPARATOR
        // =====================================================================
        $this->drawGoldSeparator($pdf, 42, 50, $W - 50);

        // =====================================================================
        //  CERTIFICATE TITLE
        // =====================================================================
        $pdf->SetXY(0, 47);
        $pdf->SetFont('helvetica', '', 12);
        $this->setText($pdf, self::GOLD_PRIMARY);
        $pdf->Cell($W, 6, chr(0xE2).chr(0x80).chr(0x94)." ATTESTATION ".chr(0xE2).chr(0x80).chr(0x94), 0, 1, 'C');

        $pdf->SetXY(0, 55);
        $pdf->SetFont('helvetica', 'B', 28);
        $this->setText($pdf, self::OFPPT_DARK);
        $pdf->Cell($W, 14, "CERTIFICAT DE PARTICIPATION", 0, 1, 'C');

        // =====================================================================
        //  BODY TEXT — "Ce certificat est décerné à"
        // =====================================================================
        $pdf->SetXY(0, 74);
        $pdf->SetFont('helvetica', '', 12);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell($W, 7, "Ce certificat est décerné avec fierté à", 0, 1, 'C');

        // =====================================================================
        //  STUDENT NAME — prominent display with gold underline
        // =====================================================================
        $pdf->SetXY(0, 85);
        $pdf->SetFont('helvetica', 'B', 26);
        $this->setText($pdf, self::OFPPT_DARK);
        $studentUpper = mb_strtoupper($this->student_name, 'UTF-8');
        $pdf->Cell($W, 12, $studentUpper, 0, 1, 'C');

        // Gold underline beneath the name
        $nameW = $pdf->GetStringWidth($studentUpper);
        $nameX = ($W - $nameW) / 2;
        $underY = 99;
        $this->setDraw($pdf, self::GOLD_PRIMARY);
        $pdf->SetLineWidth(1);
        $pdf->Line($nameX - 15, $underY, $nameX + $nameW + 15, $underY);
        // Thin accent line
        $pdf->SetLineWidth(0.3);
        $pdf->Line($nameX - 10, $underY + 1.5, $nameX + $nameW + 10, $underY + 1.5);

        // =====================================================================
        //  GROUP
        // =====================================================================
        $pdf->SetXY(0, 103);
        $pdf->SetFont('helvetica', '', 11);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell($W, 6, "Stagiaire du groupe", 0, 1, 'C');

        $pdf->SetXY(0, 110);
        $pdf->SetFont('helvetica', 'B', 16);
        $this->setText($pdf, self::OFPPT_MEDIUM);
        $pdf->Cell($W, 8, mb_strtoupper($this->group, 'UTF-8'), 0, 1, 'C');

        // =====================================================================
        //  ACTIVITY
        // =====================================================================
        $pdf->SetXY(0, 122);
        $pdf->SetFont('helvetica', '', 11);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell($W, 6, "Pour sa participation et son engagement dans le cadre de :", 0, 1, 'C');

        $pdf->SetXY(0, 130);
        $pdf->SetFont('helvetica', 'BI', 15);
        $this->setText($pdf, self::OFPPT_DARK);
        $pdf->Cell($W, 8, chr(0xC2).chr(0xAB)." ".$this->activity_title." ".chr(0xC2).chr(0xBB), 0, 1, 'C');

        // =====================================================================
        //  BOTTOM GOLD SEPARATOR
        // =====================================================================
        $this->drawGoldSeparator($pdf, 144, 50, $W - 50);

        // =====================================================================
        //  FOOTER — Date (left) & Signature (right)
        // =====================================================================
        $footerY = 152;

        // --- Left: Date & Location ---
        $pdf->SetXY(30, $footerY);
        $pdf->SetFont('helvetica', '', 10);
        $this->setText($pdf, self::TEXT_MID);
        $months = ['01'=>'janvier','02'=>'février','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin',
                   '07'=>'juillet','08'=>'août','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'décembre'];
        $day = date('d');
        $month = $months[date('m')] ?? date('m');
        $year = date('Y');
        $pdf->Cell(100, 5, "Fait le {$day} {$month} {$year}", 0, 1, 'L');

        // --- Right: Signature block ---
        $sigCenterX = $W - 75;
        $sigBlockW = 80;

        // "Le Formateur" label (top)
        $pdf->SetXY($sigCenterX - $sigBlockW/2, $footerY);
        $pdf->SetFont('helvetica', '', 10);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell($sigBlockW, 5, "Le Formateur", 0, 1, 'C');

        // Teacher name (below label)
        $pdf->SetXY($sigCenterX - $sigBlockW/2, $footerY + 6);
        $pdf->SetFont('helvetica', 'B', 12);
        $this->setText($pdf, self::OFPPT_DARK);
        $pdf->Cell($sigBlockW, 5, $this->teacher_name, 0, 1, 'C');

        // Signature line (space for actual signature)
        $sigLineY = $footerY + 24;
        $this->setDraw($pdf, self::OFPPT_DARK);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($sigCenterX - 30, $sigLineY, $sigCenterX + 30, $sigLineY);

        // "Signature & Cachet" label
        $pdf->SetXY($sigCenterX - $sigBlockW/2, $sigLineY + 1);
        $pdf->SetFont('helvetica', 'I', 8);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell($sigBlockW, 4, "Signature & Cachet", 0, 1, 'C');

        // =====================================================================
        //  BOTTOM BAR — mirror of top
        // =====================================================================
        $this->setFill($pdf, self::OFPPT_DARK);
        $pdf->Rect(12.2, $H - 13.7, $W - 24.4, 1.5, 'F');

        // =====================================================================
        //  BOTTOM FOOTER — Reference & OFPPT tagline
        // =====================================================================
        $pdf->SetXY(20, $H - 19);
        $pdf->SetFont('helvetica', '', 7);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell(80, 4, "Réf : " . $this->generateRefNumber(), 0, 0, 'L');

        $pdf->SetXY(0, $H - 19);
        $pdf->SetFont('helvetica', 'I', 8);
        $this->setText($pdf, self::OFPPT_MEDIUM);
        $pdf->Cell($W, 4, "OFPPT — La Voie de l'Avenir", 0, 0, 'C');

        $pdf->SetXY($W - 100, $H - 19);
        $pdf->SetFont('helvetica', '', 7);
        $this->setText($pdf, self::TEXT_MID);
        $pdf->Cell(80, 4, "Programme PIE — " . date('Y'), 0, 0, 'R');

        return $pdf;
    }

    /**
     * Generate and download a certificate PDF
     */
    public function generateCertificate(string $filename = "certificate.pdf")
    {
        $pdf = $this->buildPdf();
        $pdf->Output($filename, 'D');
    }

    /**
     * Generate and save a certificate PDF to a file path
     */
    public function generateCertificateToFile(string $filepath)
    {
        $pdf = $this->buildPdf();
        $pdf->Output($filepath, 'F');
    }
}
?>