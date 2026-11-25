#!/usr/bin/env python3
"""Export two review pages directly from the current, local WordPress runtime.

This optional review artifact can be opened from disk. The editable store runs
under WordPress, with the Docker setup described in setup/INSTALARE.md.
"""
import base64
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
from urllib.parse import urlsplit

from lxml import etree, html

ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT / '.runtime' / 'wordpress'
PREVIEW = ROOT / 'preview'
OUT = PREVIEW / 'dist'
ORIGIN = 'http://127.0.0.1:4173'
THEME = '/wp-content/themes/tir-on/assets/'
PAGES = {'/': Path('index.html'), '/produs/luna/': Path('produs/luna/index.html')}

# Execute the real WordPress front controller exactly once per route.
RENDER_PHP = """
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']='127.0.0.1:4173';
$_SERVER['SERVER_NAME']='127.0.0.1';
$_SERVER['SERVER_PORT']='4173';
$_SERVER['SERVER_PROTOCOL']='HTTP/1.1';
$_SERVER['REQUEST_URI']=$argv[1];
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['DOCUMENT_ROOT']=realpath('.runtime/wordpress');
require 'scripts/runtime-router.php';
"""

def export_asset(url_path):
    if url_path.startswith(THEME):
        return Path('assets') / url_path[len(THEME):]
    path = Path(url_path.lstrip('/'))
    if '..' in path.parts or not url_path.startswith(('/wp-content/', '/wp-includes/')):
        raise ValueError(f'Unexpected runtime asset {url_path}')
    source = WEB / path
    output = OUT / path
    output.parent.mkdir(parents=True, exist_ok=True)
    if not source.is_file():
        raise FileNotFoundError(source)
    shutil.copyfile(source, output)
    if output.suffix == '.css':
        for ref in re.findall(r'url\([\x22\x27]?([^\x22\x27)]+)', output.read_text()):
            target = urlsplit(ref)
            if target.scheme or ref.startswith(('#', '//')):
                continue
            child = (source.parent / target.path).resolve()
            if child.is_file() and child.is_relative_to(WEB):
                export_asset('/' + child.relative_to(WEB).as_posix())
    return path

def build_page(route, dest):
    rendered = subprocess.run(
        ['bash', 'scripts/runtime-php.sh', '-r', RENDER_PHP, route],
        check=True, cwd=ROOT, capture_output=True, text=True,
    )
    if rendered.stderr:
        raise RuntimeError(rendered.stderr)
    doc = html.document_fromstring(rendered.stdout)
    if not doc.xpath('//h1') or doc.xpath('//*[@id="error-page"]'):
        raise RuntimeError(f'Invalid WordPress response for {route}')
    # Offline review uses the live theme UI and can only preview a photo locally.
    for link in doc.xpath('//a[contains(concat(" ",normalize-space(@class)," ")," bag-trigger ")]'):
        link.drop_tree()
    for form in doc.xpath('//form[@data-personalization]'):
        form.attrib.pop('action', None)
        for node in form.xpath('.//input[@type="hidden"]'):
            node.drop_tree()
        for button in form.xpath('.//button[@data-add-product]'):
            button.set('type', 'button')
            button.set('disabled', 'disabled')
        for tip in form.xpath('./p[contains(text(),"Fotografia se încarcă")]'):
            tip.text = 'Previzualizare offline. Fotografia rămâne pe dispozitiv. Comenzile se plasează din magazinul WordPress.'
    for meta in doc.xpath('//meta[@name="robots"]'):
        meta.set('content', 'noindex,nofollow')
    # The exported UI has local interactions, without WordPress network APIs.
    for el in doc.xpath('//script'):
        if THEME not in el.get('src',''):
            el.drop_tree()
    for el in doc.xpath('//link'):
        if el.get('rel') not in ('stylesheet','preload','icon'):
            el.drop_tree()
    for el in doc.xpath('//meta[starts-with(@property,"og:")]'):
        el.drop_tree()
    def local_url(value):
        if not value.startswith(ORIGIN + '/'):
            return value
        parsed = urlsplit(value)
        if parsed.path in PAGES:
            target = PAGES[parsed.path]
        elif parsed.path.startswith(('/wp-content/', '/wp-includes/')):
            target = export_asset(parsed.path)
        else:
            raise ValueError(f'Link outside the two exported routes: {value}')
        relative = os.path.relpath(target, dest.parent)
        return relative + ('#' + parsed.fragment if parsed.fragment else '')

    for el in doc.iter():
        for key, value in list(el.attrib.items()):
            if key in ('srcset', 'data-srcset', 'imagesrcset'):
                candidates = []
                for candidate in value.split(','):
                    url, *descriptors = candidate.strip().split()
                    candidates.append(' '.join([local_url(url), *descriptors]))
                el.set(key, ', '.join(candidates))
            elif value.startswith(ORIGIN):
                el.set(key, local_url(value))
    output = OUT / dest
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_bytes(etree.tostring(doc, encoding='utf-8', method='html', doctype='<!doctype html>'))

def export():
    OUT.mkdir(parents=True, exist_ok=True)
    shutil.copytree(ROOT / 'theme/tir-on/assets', OUT / 'assets', dirs_exist_ok=True)
    # Some browsers require same-origin CORS for font files under file://.
    font_css = OUT / 'assets/fonts.css'
    def inline_font(match):
        font = (font_css.parent / match.group(1)).read_bytes()
        return 'url(data:font/woff2;base64,' + base64.b64encode(font).decode() + ')'
    font_css.write_text(re.sub(r"""url\(['"]([^'"]+\.woff2)['"]\)""", inline_font, font_css.read_text()))
    for route, page in PAGES.items():
        build_page(route,page)
    manifest = PREVIEW / '.openai/hosting.json'
    manifest.parent.mkdir(exist_ok=True)
    manifest.write_text(json.dumps({'static': {'directory': 'dist'}}, indent=2) + '\n')

if __name__ == '__main__':
    export()
