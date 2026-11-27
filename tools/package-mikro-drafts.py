"""Build an installable ZIP using the operator's locally supplied Mikro template."""
import argparse
from io import BytesIO
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from xml.etree import ElementTree as ET
from xml.dom import minidom

NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
PLUGIN = Path(__file__).resolve().parents[1] / 'wp-content/plugins/mdg-mikro-taslak'
TEMPLATE = 'eArsivTaslakExcelSablonu.xlsx'


def empty_template(source):
    # Use DOM for the rewritten parts so Excel's namespace prefixes on extension lists survive.
    output = BytesIO()
    with ZipFile(source) as old, ZipFile(output, 'w', ZIP_DEFLATED) as new:
        parts = {name: old.read(name) for name in old.namelist()}
        workbook = ET.fromstring(parts['xl/workbook.xml'])
        sheets = workbook.findall(f'.//{{{NS}}}sheet')
        if [s.get('name') for s in sheets[:2]] != ['Fatura_Bilgileri', 'Kalem_Bilgileri']:
            raise ValueError('Mikro taslak şablonu bekleniyor.')
        used = set()
        for name in [k for k in parts if k.startswith('xl/worksheets/sheet') and k.endswith('.xml')]:
            doc = minidom.parseString(parts[name])
            clean = name in ('xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml')
            for row in doc.getElementsByTagNameNS(NS, 'row'):
                for cell in row.getElementsByTagNameNS(NS, 'c'):
                    if clean and int(row.getAttribute('r')) > 1:
                        while cell.firstChild:
                            cell.removeChild(cell.firstChild)
                        if cell.hasAttribute('t'):
                            cell.removeAttribute('t')
                    elif cell.getAttribute('t') == 's':
                        used.add(int(cell.getElementsByTagNameNS(NS, 'v')[0].firstChild.data))
            if clean:
                parts[name] = doc.toxml(encoding='utf-8')
        shared = minidom.parseString(parts['xl/sharedStrings.xml'])
        for i, item in enumerate(shared.getElementsByTagNameNS(NS, 'si')):
            if i not in used:
                while item.firstChild:
                    item.removeChild(item.firstChild)
                item.appendChild(shared.createElementNS(NS, 't'))
        parts['xl/sharedStrings.xml'] = shared.toxml(encoding='utf-8')
        for name, blob in parts.items():
            new.writestr(name, blob)
    return output.getvalue()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operator_template', type=Path)
    parser.add_argument('output', type=Path)
    args = parser.parse_args()
    with ZipFile(args.output, 'w', ZIP_DEFLATED) as package:
        for path in sorted(PLUGIN.glob('*.php')):
            package.write(path, 'mdg-mikro-taslak/' + path.name)
        package.writestr('mdg-mikro-taslak/assets/' + TEMPLATE, empty_template(args.operator_template))
