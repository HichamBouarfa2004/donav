<?php
require_once(__DIR__ . '/tcpdf/tcpdf.php');

class CertificateGenerator {
    private string $student_name, $group;
    private string $teacher_name;
    private string $activity_title;

    public function __construct(string $student_name, string $group, string $teacher_name = "Teacher", string $activity_title = "Soft Skills Activity") {
        $this->student_name = $student_name;
        $this->group = $group;
        $this->teacher_name = $teacher_name;
        $this->activity_title = $activity_title;
    }

    public function generateCertificate(string $filename = "certificate.pdf") {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('OFPPT');
        $pdf->SetAuthor('OFPPT');
        $pdf->SetTitle('Participation Certificate');
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetTextColor(0, 70, 130);
        $pdf->Cell(0, 20, "Certificate of Participation", 0, 1, 'C');

        // Body text
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->Ln(10);
        $pdf->MultiCell(0, 10, "This certificate is proudly presented to", 0, 'C');

        // Student name
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, strtoupper($this->student_name), 0, 1, 'C');

        // Group info
        $pdf->SetFont('helvetica', '', 14);
        $pdf->Ln(3);
        $pdf->MultiCell(0, 10, "from Group " . strtoupper($this->group), 0, 'C');

        // Activity title
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->MultiCell(0, 10, "for actively participating in the:", 0, 'C');

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 90, 160);
        $pdf->Cell(0, 10, strtoupper($this->activity_title), 0, 1, 'C');

        // Footer
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(15);
        $pdf->MultiCell(0, 10, "Date: " . date("F j, Y"), 0, 'L');

        $pdf->Ln(15);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, "_______________________", 0, 1, 'R');
        $pdf->Cell(0, 5, $this->teacher_name, 0, 1, 'R');
        $pdf->Cell(0, 5, "Instructor", 0, 1, 'R');

        $pdf->Output($filename, 'D'); 
    }
}
?>
