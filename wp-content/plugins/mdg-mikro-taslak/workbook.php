<?php
defined( 'ABSPATH' ) || exit;

final class MDG_Mikro_Workbook {
    const NAME = 'eArsivTaslakExcelSablonu.xlsx';
    const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private static function worksheet( $xml, $values ) {
        $doc = new DOMDocument();
        if ( ! $doc->loadXML( $xml, LIBXML_NONET ) ) {
            throw new RuntimeException( 'Mikro şablonu okunamadı.' );
        }
        $xpath = new DOMXPath( $doc );
        $xpath->registerNamespace( 's', self::NS );
        foreach ( $xpath->query( '//s:sheetData/s:row' ) as $row ) {
            $index = (int) $row->getAttribute( 'r' );
            if ( $index <= 1 ) { continue; }
            foreach ( $xpath->query( 's:c', $row ) as $cell ) {
                // Preserve the template's styles, ranges, lists and relationships. Drop its example values.
                while ( $cell->firstChild ) { $cell->removeChild( $cell->firstChild ); }
                $cell->removeAttribute( 't' );
                $key = preg_replace( '/[0-9]/', '', $cell->getAttribute( 'r' ) );
                $value = $values[ $index - 2 ][ $key ] ?? null;
                if ( null === $value || '' === $value ) { continue; }
                if ( is_int( $value ) || is_float( $value ) ) {
                    $cell->appendChild( $doc->createElementNS( self::NS, 'v', sprintf( '%.12F', $value ) ) );
                } else {
                    $cell->setAttribute( 't', 'inlineStr' );
                    $is = $cell->appendChild( $doc->createElementNS( self::NS, 'is' ) );
                    $t = $is->appendChild( $doc->createElementNS( self::NS, 't' ) );
                    $text = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value );
                    $t->appendChild( $doc->createTextNode( $text ) );
                }
            }
        }
        return $doc->saveXML();
    }

    public static function bytes( $docs, $series, $first, $issued_at, $template = null ) {
        $heads = array();
        $items = array();
        foreach ( $docs as $offset => $data ) {
            $n = $offset + 1;
            $heads[] = array( 'A' => $n, 'B' => 'Satış', 'C' => $series, 'D' => $first + $offset,
                'E' => MDG_Mikro_Draft::date( 'd/m/Y', $issued_at ), 'F' => MDG_Mikro_Draft::date( 'H:i', $issued_at ),
                'G' => 'Türk Lirası', 'H' => 1, 'I' => 'Kredi Kartı/Banka Kartı', 'J' => 'madagaskarsirki.com',
                'K' => MDG_Mikro_Draft::date( 'd/m/Y', $data['paid'] ), 'L' => 'Bu satış internet üzerinden yapılmıştır. PayTR.' ) + $data['customer'];
            foreach ( $data['lines'] as $index => $line ) {
                $items[] = array( 'A' => $n, 'B' => $index + 1 ) + $line;
            }
        }
        if ( ! $heads || count( $heads ) > 100 || count( $items ) > 100 ) {
            throw new RuntimeException( 'Dosya en fazla 100 fatura ve 100 kalem içerebilir.' );
        }
        $path = tempnam( get_temp_dir(), 'mdg-xlsx-' );
        if ( false === $path ) { throw new RuntimeException( 'Geçici dosya oluşturulamadı.' ); }
        try {
            if ( ! copy( $template ?? __DIR__ . '/assets/' . self::NAME, $path ) ) { throw new RuntimeException( 'Şablon kopyalanamadı.' ); }
            $zip = new ZipArchive();
            if ( true !== $zip->open( $path ) ) { throw new RuntimeException( 'XLSX açılamadı.' ); }
            foreach ( array( 1 => $heads, 2 => $items ) as $sheet => $rows ) {
                $part = 'xl/worksheets/sheet' . $sheet . '.xml';
                if ( ! $zip->addFromString( $part, self::worksheet( $zip->getFromName( $part ), $rows ) ) ) {
                    throw new RuntimeException( 'XLSX satırları yazılamadı.' );
                }
            }
            if ( ! $zip->close() ) { throw new RuntimeException( 'XLSX kaydedilemedi.' ); }
            $bytes = file_get_contents( $path );
            if ( ! $bytes ) { throw new RuntimeException( 'XLSX okunamadı.' ); }
            return $bytes;
        } finally { unlink( $path ); }
    }
}
