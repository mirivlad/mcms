#!/usr/bin/env python3

from html.parser import HTMLParser
import sys


class CsrfParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.token = None

    def handle_starttag(self, tag, attrs) -> None:
        if tag != "input":
            return

        values = dict(attrs)
        if values.get("name") == "csrf_test_name":
            self.token = values.get("value")


if len(sys.argv) != 2:
    raise SystemExit("usage: extract-csrf.py <html-file>")

parser = CsrfParser()
with open(sys.argv[1], encoding="utf-8") as handle:
    parser.feed(handle.read())

if not parser.token:
    raise SystemExit("CSRF token not found")

print(parser.token)
