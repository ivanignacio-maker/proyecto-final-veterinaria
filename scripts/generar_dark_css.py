"""Genera assets/dark.generated.css: versión oscura de los colores fijos del sitio.

Uso (desde la raíz del proyecto):  python scripts/generar_dark_css.py

Lee las hojas de estilo y los style="" de los .php, y por cada regla que define
colores emite una copia bajo html[data-theme="dark"] con los colores convertidos:
fondos claros -> superficies oscuras, textos oscuros -> claros, bordes claros -> oscuros.
Las reglas que solo usan variables (var(--...)) se copian igual para no alterar la cascada;
las variables en sí se redefinen a mano en assets/dark.css.
Volver a correrlo cada vez que se cambien colores en el CSS.
"""
import colorsys
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent
SOURCES = ['assets/style.css', 'assets/public.css', 'assets/login.css']
EMBEDDED = ['cliente/veterinarias_cercanas.php']  # páginas con <style> propio
OUT = ROOT / 'assets' / 'dark.generated.css'
DARK = 'html[data-theme="dark"]'

COLOR_RE = re.compile(r'#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b|rgba?\([^)]*\)|\b(?:white|black)\b')
SKIP_PROPS = {'border-radius', 'border-width', 'border-style', 'border-collapse', 'border-spacing',
              'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius',
              'border-bottom-right-radius', 'outline-offset', 'outline-width', 'outline-style'}
# Sub-propiedades de fondo: no tienen color, pero el atajo "background" de la copia oscura
# las resetea, así que se vuelven a emitir tal cual (p. ej. texto con degradado vía background-clip).
BG_LONGHANDS = {'background-size', 'background-position', 'background-repeat', 'background-clip',
                '-webkit-background-clip', 'background-attachment', 'background-blend-mode',
                'background-origin'}


def category(prop):
    if prop in SKIP_PROPS:
        return None
    if prop in BG_LONGHANDS:
        return 'keep'
    if prop.startswith('background'):
        return 'bg'
    if prop in ('color', '-webkit-text-fill-color', 'fill', 'stroke', 'caret-color', 'text-decoration-color'):
        return 'text'
    if prop.startswith('border') or prop.startswith('outline'):
        return 'border'
    if prop in ('box-shadow', 'text-shadow'):
        return 'shadow'
    return None


def parse(c):
    c = c.lower()
    if c == 'white':
        return 255, 255, 255, 1.0
    if c == 'black':
        return 0, 0, 0, 1.0
    if c.startswith('#'):
        h = c[1:]
        if len(h) == 3:
            h = ''.join(ch * 2 for ch in h)
        return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16), 1.0
    nums = [n.strip() for n in c[c.index('(') + 1:-1].split(',')]
    if len(nums) < 3:
        return None
    a = float(nums[3]) if len(nums) > 3 else 1.0
    return int(float(nums[0])), int(float(nums[1])), int(float(nums[2])), a


def hls(r, g, b):
    return colorsys.rgb_to_hls(r / 255, g / 255, b / 255)


def fmt(h, l, s, a):
    r, g, b = (round(v * 255) for v in colorsys.hls_to_rgb(h, max(0, min(1, l)), max(0, min(1, s))))
    if a >= 1:
        return '#%02x%02x%02x' % (r, g, b)
    return 'rgba(%d, %d, %d, %s)' % (r, g, b, ('%.3f' % a).rstrip('0').rstrip('.'))


GREEN_HUE = 160 / 360


def convert(c, cat):
    p = parse(c)
    if not p:
        return c
    r, g, b, a = p
    h, l, s = hls(r, g, b)
    if s < 0.05:  # grises/blancos: teñirlos levemente de verde como el resto del sitio
        h, s = GREEN_HUE, 0.18
    if cat == 'bg':
        if l >= 0.8:
            return fmt(h, 0.11 + (1 - l) * 0.55, min(s, 0.32), a)
        return c
    if cat == 'text':
        if l < 0.6:
            return fmt(h, 0.93 - l * 0.55, min(s, 0.55), a)
        return c
    if cat == 'border':
        if l >= 0.75:
            return fmt(h, 0.2 + (1 - l) * 0.5, min(s, 0.25), a)
        return c
    if cat == 'shadow':
        if l < 0.5:
            return 'rgba(0, 0, 0, %s)' % ('%.2f' % min(a * 2.5, 0.6)).rstrip('0').rstrip('.')
        return c
    return c


def is_light_kept_bg(value):
    """Fondo que no se oscurece pero es claro (p. ej. dorado): su texto debe quedar oscuro."""
    for m in COLOR_RE.finditer(value):
        p = parse(m.group(0))
        if p and p[3] > 0.5:
            l = hls(*p[:3])[1]
            if 0.55 <= l < 0.8:
                return True
    return False


def split_decls(body):
    decls, depth, cur = [], 0, ''
    for ch in body:
        if ch == '(':
            depth += 1
        elif ch == ')':
            depth -= 1
        if ch == ';' and depth == 0:
            decls.append(cur)
            cur = ''
        else:
            cur += ch
    decls.append(cur)
    out = []
    for d in decls:
        if ':' in d:
            k, v = d.split(':', 1)
            if k.strip():
                out.append((k.strip().lower(), v.strip()))
    return out


def split_selectors(sel):
    parts, depth, cur = [], 0, ''
    for ch in sel:
        if ch in '([':
            depth += 1
        elif ch in ')]':
            depth -= 1
        if ch == ',' and depth == 0:
            parts.append(cur.strip())
            cur = ''
        else:
            cur += ch
    parts.append(cur.strip())
    return [p for p in parts if p]


def prefix(sel):
    s = ' '.join(sel.split())
    if s.startswith(':root'):
        return DARK + s[5:]
    if re.match(r'html\b', s):
        return DARK + s[4:]
    return DARK + ' ' + s


def transform_rule(selector, body):
    decls = split_decls(body)
    bg_light_kept = any(category(k) == 'bg' and is_light_kept_bg(v) for k, v in decls)
    out = []
    for k, v in decls:
        cat = category(k)
        if not cat:
            continue
        if cat == 'keep' or (cat == 'text' and bg_light_kept):
            out.append((k, v))
            continue
        out.append((k, COLOR_RE.sub(lambda m: convert(m.group(0), cat), v)))
    if all(k in BG_LONGHANDS for k, _ in out):
        return None
    sels = ',\n'.join(prefix(s) for s in split_selectors(selector))
    return sels + ' {\n' + ''.join('    %s: %s;\n' % d for d in out) + '}\n'


def blocks(css):
    """Recorre el CSS y devuelve (prelude, cuerpo) de primer nivel."""
    i, n = 0, len(css)
    while i < n:
        j = css.find('{', i)
        if j < 0:
            return
        prelude = css[i:j].strip()
        # @import y similares terminan en ';' antes de la llave
        while ';' in prelude and prelude.startswith('@'):
            prelude = prelude.split(';', 1)[1].strip()
        depth, k = 1, j + 1
        while k < n and depth:
            if css[k] == '{':
                depth += 1
            elif css[k] == '}':
                depth -= 1
            k += 1
        yield prelude, css[j + 1:k - 1]
        i = k


def process(css):
    css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    out = []
    for prelude, body in blocks(css):
        if prelude.startswith('@media') or prelude.startswith('@supports'):
            inner = [r for p, b in blocks(body) if (r := transform_rule(p, b))]
            if inner:
                out.append(prelude + ' {\n' + '\n'.join(inner) + '}\n')
        elif prelude.startswith('@'):
            continue  # keyframes, font-face
        else:
            r = transform_rule(prelude, body)
            if r:
                out.append(r)
    return out


def inline_overrides():
    """style="" con colores fijos en los .php -> reglas con !important por selector de atributo."""
    rules = {}
    for php in sorted(ROOT.rglob('*.php')):
        if 'scripts' in php.parts:
            continue
        for style in re.findall(r'style="([^"]*)"', php.read_text(encoding='utf-8', errors='ignore')):
            decls = [d for d in style.split(';') if ':' in d and '<?' not in d]
            parsed = [(d.split(':', 1)[0].strip().lower(), d.split(':', 1)[1].strip(), d.strip()) for d in decls]
            bg_light_kept = any(category(k) == 'bg' and is_light_kept_bg(v) for k, v, _ in parsed)
            changes, keys = [], []
            for k, v, raw in parsed:
                cat = category(k)
                if not cat or 'var(' in v or not COLOR_RE.search(v):
                    continue
                if cat == 'text' and bg_light_kept:
                    continue
                nv = COLOR_RE.sub(lambda m: convert(m.group(0), cat), v)
                if nv != v:
                    if cat == 'border' and k in ('border', 'border-top', 'border-bottom', 'border-left', 'border-right'):
                        k, nv = k + '-color', COLOR_RE.search(nv).group(0)
                    changes.append((k, nv))
                    keys.append(raw.replace('"', '\\"'))
            if changes:
                sel = DARK + ' ' + ''.join('[style*="%s"]' % key for key in keys)
                rules[sel] = changes
    return [sel + ' {\n' + ''.join('    %s: %s !important;\n' % c for c in ch) + '}\n' for sel, ch in rules.items()]


def main():
    parts = ['/* ARCHIVO GENERADO por scripts/generar_dark_css.py - no editar a mano. */\n']
    for src in SOURCES:
        parts.append('\n/* ---- %s ---- */\n' % src)
        parts += process((ROOT / src).read_text(encoding='utf-8'))
    for php in EMBEDDED:
        text = (ROOT / php).read_text(encoding='utf-8')
        for css in re.findall(r'<style>(.*?)</style>', text, flags=re.S):
            parts.append('\n/* ---- %s ---- */\n' % php)
            parts += process(css)
    parts.append('\n/* ---- estilos en línea de los .php ---- */\n')
    parts += inline_overrides()
    OUT.write_text('\n'.join(parts), encoding='utf-8')
    print('OK:', OUT.relative_to(ROOT), sum(p.count('{') for p in parts), 'reglas')


if __name__ == '__main__':
    main()
