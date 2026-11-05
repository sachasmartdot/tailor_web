<?php
declare(strict_types=1);
namespace App\Support;

/**
* Fabrique un PDF très simple (texte seulement) sans bibliothèque externe.
* Entrée : une liste de lignes. La première est le titre.
*/
class ReceiptPdf {
	private const LIGNES_PAR_PAGE = 45;
	
	public static function build(array $lignes): string {
		$pages = array_chunk($lignes, self::LIGNES_PAR_PAGE);
		
		// objets 1 à 4 : catalogue, liste des pages, deux polices. Ensuite 2 objets par page.
		$objets = [];
		$kids = [];
		
		foreach ($pages as $n => $lignesPage) {
			$flux = "BT\n";
			$y = 790;
			
			foreach ($lignesPage as $i => $texte) {
				$titre = ($n === 0 && $i === 0);
				$flux .= ($titre ? '/F2 18' : '/F1 11') . " Tf 1 0 0 1 50 $y Tm (" . self::texte((string) $texte) . ") Tj\n";
				$y -= $titre ? 34 : 16;
			}
			$flux .= "ET";
			
			$numContenu = 5 + $n * 2;
			$numPage = 6 + $n * 2;
			$objets[$numContenu] = "<< /Length " . strlen($flux) . " >>\nstream\n" . $flux . "\nendstream";
			$objets[$numPage] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents $numContenu 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>";
			$kids[] = "$numPage 0 R";
		}
		
		$objets[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objets[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
		$objets[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objets[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		ksort($objets);
		
		$pdf = "%PDF-1.4\n";
		$positions = [];
		foreach ($objets as $num => $contenu) {
			$positions[$num] = strlen($pdf);
			$pdf .= "$num 0 obj\n$contenu\nendobj\n";
		}
		
		$xref = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objets) + 1) . "\n0000000000 65535 f \n";
		foreach ($positions as $pos) {
			$pdf .= sprintf("%010d 00000 n \n", $pos);
		}
		$pdf .= "trailer\n<< /Size " . (count($objets) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
		
		return $pdf;
	}
	
	// UTF-8 vers Windows-1252 (accents), et échappement des caractères spéciaux du PDF
	private static function texte(string $s): string {
		$s = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);   // accents (iconv est toujours disponible)
		return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
	}
}
