"""Serve the BBT static site locally so changes can be previewed in a browser.

    python serve.py            # serves on the first free port from 8000, opens a browser
    python serve.py 9000       # pick the port yourself
    python serve.py --no-open  # don't launch a browser

The site is plain HTML with relative links, so a static file server is all it
needs. Nothing is built and nothing is written.
"""
import http.server
import os
import socket
import socketserver
import sys
import threading
import webbrowser

ROOT = os.path.dirname(os.path.abspath(__file__))

# Pages worth looking at after the recent changes.
HIGHLIGHTS = [
    ('index.html', 'Home - Corporate now sits in the navbar, after NAVTTC'),
    ('corporate-training.html', 'The new Corporate AI Training page'),
    ('insights.html', 'Insights - now just Blog and FAQs'),
    ('navttc.html', 'NAVTTC - a normal page, to check the nav looks right'),
]


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=ROOT, **kw)

    def end_headers(self):
        # never serve a stale page while you are editing
        self.send_header('Cache-Control', 'no-store, must-revalidate')
        super().end_headers()

    def log_message(self, fmt, *args):
        status = args[1] if len(args) > 1 else ''
        if status.startswith('4') or status.startswith('5'):
            sys.stderr.write(f'  {status}  {args[0]}\n')


def free_port(start):
    for port in range(start, start + 50):
        with socket.socket() as s:
            if s.connect_ex(('127.0.0.1', port)) != 0:
                return port
    raise SystemExit(f'no free port between {start} and {start + 49}')


def main():
    args = [a for a in sys.argv[1:] if a != '--no-open']
    port = free_port(int(args[0]) if args else 8000)
    base = f'http://localhost:{port}'

    socketserver.TCPServer.allow_reuse_address = True
    with socketserver.TCPServer(('127.0.0.1', port), Handler) as httpd:
        print(f'\n  BBT site -> {base}')
        print(f'  serving  {ROOT}\n')
        for page, why in HIGHLIGHTS:
            print(f'    {base}/{page}')
            print(f'      {why}')
        print('\n  Ctrl+C to stop.')
        print('  Note: the chat bubble is a WordPress plugin, so it does NOT appear here.\n')

        if '--no-open' not in sys.argv:
            threading.Timer(0.5, lambda: webbrowser.open(f'{base}/index.html')).start()
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print('\n  stopped.')


if __name__ == '__main__':
    main()
