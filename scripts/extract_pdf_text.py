import sys
from pathlib import Path
from pypdf import PdfReader


def main() -> int:
    if len(sys.argv) < 2:
        print("Uso: extract_pdf_text.py <arquivo> [senha]", file=sys.stderr)
        return 2

    pdf_path = Path(sys.argv[1])
    password = sys.argv[2] if len(sys.argv) >= 3 else None

    if not pdf_path.exists():
        print("Arquivo PDF nao encontrado.", file=sys.stderr)
        return 2

    try:
        reader = PdfReader(str(pdf_path))

        if reader.is_encrypted:
            if not password:
                print("PDF protegido por senha.", file=sys.stderr)
                return 3

            result = reader.decrypt(password)
            if result == 0:
                print("Senha invalida para este PDF.", file=sys.stderr)
                return 4

        parts = []
        for page in reader.pages:
            parts.append(page.extract_text() or "")

        text = "\n".join(parts).strip()
        if not text:
            print("Nenhum texto foi extraido do PDF.", file=sys.stderr)
            return 5

        sys.stdout.buffer.write(text.encode("utf-8", errors="replace"))
        return 0
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
