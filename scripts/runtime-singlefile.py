#!/usr/bin/env python3
"""Bundle the two WordPress review exports into a stand-alone, offline HTML file."""
import base64
import json
import mimetypes
from pathlib import Path
import re
from urllib.parse import urlsplit

from lxml import etree, html

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / 'preview/dist'
HOME = DIST / 'index.html'
LUNA = DIST / 'produs/luna/index.html'
OUTPUT = ROOT / 'preview/TIR-ON-interactiv.html'
ROUTES = {HOME: 'acasa', LUNA: 'produs-luna'}

# Image bytes occur once in the file. The first hero picture also works in
# read-only viewers that permit embedded images but disable JavaScript.
HYDRATE = r"""
(() => {
  const payload = document.getElementById('offline-images');
  const assets = JSON.parse(payload.textContent);
  document.querySelectorAll('[data-original-asset]').forEach(element => {
    assets[element.dataset.originalAsset] = element.getAttribute(element.dataset.originalAttribute);
  });
  const urls = {};
  for (const [key, data] of Object.entries(assets)) {
    const cut = data.indexOf(',');
    const base64 = data.slice(cut + 1);
    let bytes;
    if (typeof Uint8Array.fromBase64 === 'function') {
      bytes = Uint8Array.fromBase64(base64);
    } else {
      const raw = atob(base64);
      bytes = new Uint8Array(raw.length);
      for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    }
    urls[key] = URL.createObjectURL(new Blob([bytes], {type: data.slice(5, data.indexOf(';'))}));
  }
  payload.remove();
  document.querySelectorAll('[data-offline-images]').forEach(element => {
    const refs = JSON.parse(element.dataset.offlineImages);
    for (const [attribute, candidates] of Object.entries(refs)) {
      if (attribute === element.dataset.originalAttribute) continue;
      element.setAttribute(attribute, candidates.map(([key, descriptor]) =>
        urls[key] + (descriptor ? ' ' + descriptor : '')).join(', '));
    }
    delete element.dataset.offlineImages;
    delete element.dataset.originalAsset;
    delete element.dataset.originalAttribute;
  });
})();
"""
ROUTER = r"""
document.addEventListener('DOMContentLoaded', () => {
  const pages = {
    acasa: document.getElementById('acasa'),
    'produs-luna': document.getElementById('produs-luna')
  };
  const skip = document.querySelector('.skip-link');
  const productLink = document.querySelector('.head-nav a[href="#produs-luna"]');
  const sections = new Map(Object.values(pages).flatMap(page =>
    Array.from(page.querySelectorAll('[id]'), node => [node.id, {page, node}])));
  let active = pages.acasa;
  let lastHash;
  pages['produs-luna'].remove();
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  function showRoute({focus = true, scrollY} = {}) {
    const key = location.hash.slice(1) || 'acasa';
    const section = sections.get(key);
    const next = pages[key] || section?.page || pages.acasa;
    const target = section?.node || next;
    if (next !== active) {
      document.querySelectorAll('dialog[open]').forEach(dialog => dialog.close());
      active.replaceWith(next);
      active = next;
    }
    next.hidden = false;
    document.body.className = next.dataset.pageClasses;
    document.title = next.dataset.pageTitle;
    skip.hash = next.id;
    if (next === pages['produs-luna']) productLink.setAttribute('aria-current', 'page');
    else productLink.removeAttribute('aria-current');
    if (focus) {
      if (!target.hasAttribute('tabindex')) target.tabIndex = -1;
      target.focus({preventScroll: true});
    }
    if (Number.isFinite(scrollY)) window.scrollTo({top: scrollY, behavior: 'instant'});
    else if (target === next) window.scrollTo({top: 0, behavior: 'instant'});
    else target.scrollIntoView({block: 'start', behavior: 'instant'});
    lastHash = location.hash;
  }

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey ||
        event.ctrlKey || event.shiftKey || event.altKey) return;
    const id = link.hash.slice(1);
    if (!pages[id] && !sections.has(id)) return;
    event.preventDefault();
    const dialog = link.closest('dialog[open]');
    if (dialog) dialog.close();
    if (link.hash !== location.hash) {
      // Some browsers restrict pushState for local files. Native fragment
      // navigation remains functional when origin-based history is unavailable.
      try {
        history.replaceState({y: window.scrollY}, '');
        history.pushState(null, '', link.hash);
      } catch {
        location.hash = link.hash;
      }
    }
    showRoute();
  });
  window.addEventListener('popstate', event => showRoute({scrollY: event.state?.y}));
  window.addEventListener('hashchange', () => {
    if (lastHash !== location.hash) showRoute();
  });
  showRoute({focus: Boolean(location.hash)});
}, {once: true});
"""


def local_file(ref, page):
    parsed = urlsplit(ref)
    if parsed.scheme or parsed.netloc or not parsed.path:
        raise ValueError(f'Expected a local file, found {ref}')
    path = (page.parent / parsed.path).resolve()
    if not path.is_relative_to(DIST) or not path.is_file():
        raise ValueError(f'Missing local asset {ref} in {page}')
    return path


def page_link(ref, page):
    parsed = urlsplit(ref)
    target = local_file(ref, page) if parsed.path else page
    if target not in ROUTES or parsed.scheme or parsed.netloc:
        raise ValueError(f'Unsupported page link {ref}')
    fragment = parsed.fragment
    return '#' + (fragment if fragment and fragment != 'continut' else ROUTES[target])


def data_uri(path):
    mime = mimetypes.guess_type(str(path))[0]
    if not mime or not mime.startswith(('image/', 'font/')):
        raise ValueError(f'Expected an image or font, found {path}')
    return 'data:' + mime + ';base64,' + base64.b64encode(path.read_bytes()).decode('ascii')


def add_script(body, contents, kind='text/javascript', ident=None):
    attributes = {'type': kind}
    if ident:
        attributes['id'] = ident
    if '</script' in contents.lower():
        raise ValueError('Unexpected script-closing tag in bundled script')
    etree.SubElement(body, 'script', attributes).text = contents


def bundle():
    docs = {path: html.parse(str(path), parser=html.HTMLParser(encoding='utf-8')) for path in ROUTES}
    home = docs[HOME]
    head, body = home.find('head'), home.find('body')
    scripts, styled, images, packed = {}, set(), {}, {}
    inline_keys = set()
    for page, doc in docs.items():
        page_body = doc.find('body')
        main = page_body.find('main')
        main.set('id', ROUTES[page])
        main.set('data-page-classes', page_body.get('class', ''))
        main.set('data-page-title', doc.findtext('head/title'))
        for node in doc.xpath('//a[@href] | //*[@data-luna-url]'):
            attr = 'href' if node.tag == 'a' else 'data-luna-url'
            node.set(attr, page_link(node.get(attr), page))
        for node in list(doc.xpath('//script[@src]')):
            path = local_file(node.get('src'), page)
            scripts[path] = path.read_text()
            node.drop_tree()
        for node in list(doc.xpath('//link')):
            if node.get('rel') == 'stylesheet':
                path = local_file(node.get('href'), page)
                if path not in styled:
                    stylesheet = path.read_text()
                    for ref in re.findall(r'url\(([^)]+)\)', stylesheet):
                        if not ref.strip(' \'"').startswith(('data:', '#')):
                            raise ValueError(f'Expected a self-contained stylesheet: {path}')
                    style = etree.SubElement(head, 'style', id=node.get('id'))
                    style.text = stylesheet
                    styled.add(path)
            if page == HOME and node.get('rel') == 'icon':
                node.set('href', data_uri(local_file(node.get('href'), page)))
                continue
            node.drop_tree()
        image_nodes = main.xpath('.//img | .//source')
        if page == HOME:
            image_nodes += doc.xpath('//header//img | //footer//img')
        for node in image_nodes:
            refs = {}
            for attribute in ('src', 'srcset', 'data-src', 'data-srcset'):
                value = node.attrib.pop(attribute, None)
                if not value:
                    continue
                refs[attribute] = []
                for candidate in value.split(','):
                    url, *dimensions = candidate.strip().split()
                    path = local_file(url, page)
                    key = images.setdefault(path, 'i' + str(len(images)))
                    if key not in packed:
                        packed[key] = data_uri(path)
                    refs[attribute].append([key, ' '.join(dimensions)])
                if attribute in ('src', 'srcset') and len(refs[attribute]) == 1:
                    key, descriptor = refs[attribute][0]
                    if not descriptor and key not in inline_keys:
                        inline_keys.add(key)
                        node.set(attribute, packed[key])
                        node.set('data-original-asset', key)
                        node.set('data-original-attribute', attribute)
            if refs:
                node.set('data-offline-images', json.dumps(refs, separators=(',', ':')))
        if page == LUNA:
            main.set('hidden', 'hidden')
            body.insert(body.index(body.find('main')) + 1, main)

    for node in home.xpath('//*[@data-original-asset]'):
        packed.pop(node.get('data-original-asset'), None)
    # Retain both OFL notices alongside the single embedded copy of each font.
    etree.SubElement(head, 'style').text = '[data-add-product][hidden] { display: none !important; }'
    licenses = sorted((DIST / 'assets/fonts').glob('*-OFL.txt'))
    add_script(body, '\n\n'.join(path.read_text() for path in licenses), 'text/plain', 'font-licenses')
    add_script(body, json.dumps(packed, separators=(',', ':')), 'application/json', 'offline-images')
    add_script(body, HYDRATE)
    for contents in scripts.values():
        add_script(body, contents)
    add_script(body, ROUTER)
    meta = etree.Element('meta', {'http-equiv': 'Content-Security-Policy', 'content':
        "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; "
        "img-src data: blob:; font-src data:; connect-src 'none'; form-action 'none'; base-uri 'none'"})
    head.insert(1, meta)
    ids = home.xpath('//@id')
    if len(ids) != len(set(ids)):
        raise ValueError('Duplicate element IDs in merged preview')
    for el in home.xpath('//a[@href] | //*[@data-luna-url]'):
        target = el.get('href') or el.get('data-luna-url')
        if target[1:] not in ids:
            raise ValueError(f'Missing navigation target: {target}')
    output = etree.tostring(home, method='html', encoding='utf-8', doctype='<!doctype html>')
    OUTPUT.parent.mkdir(exist_ok=True)
    OUTPUT.write_bytes(output)
    print(f'{OUTPUT.name} · {len(output):,} bytes · {len(images)} images · {len(ROUTES)} views')


if __name__ == '__main__':
    bundle()
