#!/usr/bin/env python3
"""Generate the mobile reading edition from the WordPress HTML export and theme assets.

Run after runtime-export.py. Requires Python with lxml, Pillow, reportlab,
fontTools and brotli. Uses no network or browser.
"""
from io import BytesIO
from pathlib import Path
from tempfile import TemporaryDirectory
from PIL import Image, ImageOps
from fontTools.ttLib import TTFont as SourceFont
from fontTools.varLib.instancer import instantiateVariableFont
from lxml import html
from reportlab.lib.colors import HexColor
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen.canvas import Canvas

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "theme/tir-on/assets"
EXPORT = ROOT / "preview/dist"
OUT = ROOT / "artifacts"
OUT.mkdir(exist_ok=True)
PDF = OUT / "TIR-ON-mobil.pdf"
W, H, M = 390, 796, 24
PAPER, INK, HERO = "#F8F5EF", "#181411", "#17120F"
GOLD, NUT, MUTED, WHITE = "#DFC199", "#7B5F3C", "#5E554B", "#FFFDF9"
PALE, LIGHT, DARKLINE = "#D6C9B8", "#E2D9CD", "#655745"
PAGE_TITLES = [
    ("acasa", "Acasă"), ("obiectul", "Prezentare Luna"),
    ("descopera-lumina", "Descoperă lumina"), ("procesul", "Cum funcționează"),
    ("cadouri", "Idei de cadou"), ("luna", "Luna"),
    ("personalizare", "Personalizare")
]

def get_page(path):
    return html.fromstring(path.read_bytes(), parser=html.HTMLParser(encoding="utf-8"))

home = get_page(EXPORT / "index.html")
product = get_page(EXPORT / "produs/luna/index.html")

def classes(name):
    return f"//*[contains(concat(' ',normalize-space(@class),' '),' {name} ')]"

def get(doc, path):
    nodes = doc.xpath(path)
    if not nodes:
        raise ValueError(f"Missing export data at {path}")
    node = nodes[0]
    value = node if isinstance(node, str) else node.text_content()
    return " ".join(value.split())

def field(doc, classname):
    return get(doc, classes(classname))

with TemporaryDirectory() as tmp:
    for alias, file, weight in [
        ("Serif", "cormorant-garamond-medium.woff2", None),
        ("Italic", "cormorant-garamond-medium-italic.woff2", None),
        ("Sans", "dm-sans.woff2", 400), ("Medium", "dm-sans.woff2", 550),
    ]:
        font = SourceFont(ASSETS / "fonts" / file)
        if weight:
            font = instantiateVariableFont(font, {"wght": weight}, inplace=True)
        ttf = Path(tmp) / f"{alias}.ttf"
        font.flavor = None
        font.save(ttf)
        pdfmetrics.registerFont(TTFont(alias, str(ttf)))

    c = Canvas(str(PDF), pagesize=(W, H), pageCompression=1)
    c.setTitle("TIR-ON Luna · Prezentare pentru telefon")
    c.setAuthor("TIR-ON")
    c.setSubject("Ediție vizuală pentru telefon a paginii de prezentare și a paginii de produs")
    c.setCreator("TIR-ON • ReportLab")

    def tx(x, y, text, size=15, font="Sans", color=INK, anchor="left"):
        width = pdfmetrics.stringWidth(text, font, size)
        x = x - (width if anchor=="right" else width/2 if anchor=="center" else 0)
        if x < -0.1 or x+width > W+.1 or y<0 or y>H:
            raise ValueError(f"Text out of page bounds: {text!r} ({x}, {y}, {width})")
        c.setFillColor(HexColor(color))
        c.setFont(font,size)
        c.drawString(x,H-y,text)
        return width

    def para(x,y,text,width,size=15,lead=22,font="Sans",color=MUTED):
        line = ""
        for word in text.split():
            trial = (line+" "+word).strip()
            if line and pdfmetrics.stringWidth(trial,font,size)>width:
                tx(x,y,line,size,font,color)
                y+=lead
                line=word
            else:
                line=trial
        if line:
            tx(x,y,line,size,font,color)
        return y+lead

    def box(x,y,w,h,color,border=None,r=0):
        c.setFillColor(HexColor(color))
        if border: c.setStrokeColor(HexColor(border))
        c.setLineWidth(.8)
        if r: c.roundRect(x,H-y-h,w,h,r,stroke=bool(border),fill=1)
        else: c.rect(x,H-y-h,w,h,stroke=bool(border),fill=1)

    def rule(x1,y1,x2,y2,color,width=.8):
        c.setStrokeColor(HexColor(color));c.setLineWidth(width);c.line(x1,H-y1,x2,H-y2)

    def icon(name,cx,cy,col=INK,s=1):
        c.saveState(); c.translate(cx,H-cy);c.scale(s,-s)
        c.setStrokeColor(HexColor(col));c.setLineWidth(1.25);c.setLineCap(1);c.setLineJoin(1)
        def p(coords,closed=False):
            path=c.beginPath();path.moveTo(*coords[0])
            for a in coords[1:]:path.lineTo(*a)
            if closed:path.close()
            c.drawPath(path)
        if name=="bag":
            p([(-7,-3),(7,-3),(8,8),(-8,8)],True);q=c.beginPath();q.moveTo(-3.5,-3);q.curveTo(-3.5,-11,3.5,-11,3.5,-3);c.drawPath(q)
        elif name=="menu":
            for off in (-4,1,6):c.line(-7,off,7,off)
        elif name=="spark":
            p([(0,-9),(2,-2),(9,0),(2,2),(0,9),(-2,2),(-9,0),(-2,-2)],True)
        elif name=="photo":
            c.roundRect(-9,-7,18,15,2,fill=0); c.circle(-4,-2,1.4);p([(-8,5),(-3,1),(0,3),(4,-1),(8,4)])
        elif name=="sun":
            import math
            c.circle(0,0,4)
            for a in range(0,360,45):
                t=math.radians(a);c.line(6*math.cos(t),6*math.sin(t),9*math.cos(t),9*math.sin(t))
        elif name=="layers":
            p([(-9,-2),(0,-7),(9,-2),(0,3)],True);p([(-9,2),(0,7),(9,2)]);p([(-9,6),(0,11),(9,6)])
        elif name=="zoom":
            c.circle(-2,-2,4.5);c.line(1,1,7,7);c.line(-4,-2,0,-2);c.line(-2,-4,-2,0)
        c.restoreState()

    def image(name,x,y,w,h,focus=(.5,.5),gradient=False):
        im=Image.open(ASSETS/"images"/(name+".webp")).convert("RGB")
        im=ImageOps.fit(im,(round(w*2),round(h*2)),method=Image.Resampling.LANCZOS,centering=focus)
        if gradient:
            rows=im.height
            mask=Image.new("L",(1,rows));mask.putdata([round(255*max(0,1-i/(rows*.11))**1.5) for i in range(rows)])
            im.paste(Image.new("RGB",im.size,HERO),mask=mask.resize(im.size))
        jpeg=BytesIO();im.save(jpeg, format="JPEG", quality=87, optimize=True, progressive=False)
        c.drawImage(ImageReader(BytesIO(jpeg.getvalue())),x,H-y-h,w,h)

    def bookmark(i):
        slug,title=PAGE_TITLES[i-1]
        c.bookmarkPage(slug)
        c.addOutlineEntry(title,slug,level=0)
    def goto(slug,x,y,w,h):
        c.linkRect("",slug,(x,H-y-h,x+w,H-y),relative=0,thickness=0)
    def logo(x,y):
        a=tx(x,y,"TIR",27,"Medium",WHITE)
        a+=tx(x+a,y,"-",27,"Medium",GOLD)
        a+=tx(x+a,y,"ON",27,"Medium",WHITE)
        c.setFillColor(HexColor(GOLD));c.circle(x+a+4,H-y+2,2.25,fill=1,stroke=0)
    def head(i,bg=PAPER):
        bookmark(i)
        box(0,0,W,H,bg);box(0,0,W,59,INK);logo(M,39)
        icon("bag",276,29,WHITE,.93);tx(292,34,"Coș",13,"Sans",WHITE);icon("menu",355,29,WHITE,1.05)
        goto("acasa",M,7,106,46)
    def foot(i,dark=False):
        linecol,color=(DARKLINE,PALE) if dark else (LIGHT,MUTED)
        rule(M,748,W-M,748,linecol)
        tx(M,774,"TIR-ON  ·  Ediție vizuală pentru telefon",11.6,"Sans",color)
        tx(W-M,774,f"{i} / {len(PAGE_TITLES)}",11.6,"Sans",color,"right")
        c.showPage()
    def kicker(text,y,dark=False):
        col=GOLD if dark else NUT
        rule(M,y-4,M+26,y-4,col,1)
        tx(M+37,y,text.upper(),11.8,"Medium",col)
    def button(x,y,text,target,width=143,dark=True):
        color,fg=(INK,WHITE) if dark else (GOLD,INK)
        box(x,y,width,44,color,r=4);tx(x+width/2,y+28,text,14,"Medium",fg,"center");goto(target,x,y,width,44)

    head(1,HERO)
    tx(M,134,get(home,"//h1/text()"),48,"Serif",WHITE)
    tx(M,182,get(home,"//h1/em"),48,"Italic",GOLD)
    para(M,219,field(home,"hero-intro"),W-2*M,15,20,color=PALE)
    box(M,288,159,44,GOLD,r=4);icon("spark",M+20,310,INK,.82)
    tx(M+36,316,"Creează-ți Luna",14,"Medium",INK);goto("luna",M,288,159,44)
    tx(205,315,field(home,"hero-sub"),14,"Medium",WHITE);rule(205,320,322,320,GOLD,.65);goto("descopera-lumina",199,288,146,44)
    image("hero",0,340,W,312,focus=(1,.5),gradient=True)
    tx(M,639,"01 / 03",11.3,"Medium",WHITE)
    tx(W-M,639,"Imagine de concept",11.3,"Sans",WHITE,"right")
    box(0,652,W,78,"#EEE8DD")
    tags=[("photo","Fotografia ta,","în relief"),("sun","Lumină caldă",""),("spark","Creată special","pentru tine")]
    for i,(symbol,a,b) in enumerate(tags):
        center=(i+.5)*W/3;icon(symbol,center,672,NUT,.88);tx(center,696,a,12.1,"Sans",MUTED,"center");tx(center,713,b,12.1,"Sans",MUTED,"center")
        if i:rule(i*W/3,674,i*W/3,710,"#C8BCAC")
    foot(1,True)

    head(2)
    tx(M,164,get(home,"//section[@id='luna']//h2"),53,"Serif")
    image("feature-mobile",M,192,W-2*M,227)
    tx(M,442,"Imagine de concept",12,"Sans",MUTED)
    description_y=para(M,480,field(home,"feature-subtitle"),W-2*M,24,26,font="Serif",color=NUT)
    para(M,description_y+10,field(home,"feature-description"),W-2*M,17,24)
    button(M,665,"Descoperă Luna","luna");tx(193,693,field(product,"lp-price"),17,"Medium")
    foot(2)

    head(3,"#211B16");kicker("Efectul luminii",101,True)
    intro_y=para(M,152,get(home,"//*[@id='tl-transform-title']"),W-2*M,39,41,font="Serif",color=WHITE)
    para(M,intro_y+10,field(home,"tl-intro"),W-2*M,15,21,color=PALE)
    x,y,w,h=M,365,W-2*M,210
    image("relief-mobile",x,y,w,h)
    c.saveState();clip=c.beginPath();clip.rect(x,H-y-h,w/2,h);c.clipPath(clip,stroke=0)
    image("original-mobile",x,y,w,h);c.restoreState()
    mid=x+w/2;rule(mid,y,mid,y+h,WHITE,1.8);c.setFillColor(HexColor(WHITE));c.circle(mid,H-y-h/2,13,fill=1,stroke=0)
    rule(mid-6,y+h/2,mid-2,y+h/2-4,MUTED,1.3);rule(mid-6,y+h/2,mid-2,y+h/2+4,MUTED,1.3)
    rule(mid+6,y+h/2,mid+2,y+h/2-4,MUTED,1.3);rule(mid+6,y+h/2,mid+2,y+h/2+4,MUTED,1.3)
    tx(M,595,"Fotografie",13,"Medium",WHITE);tx(W-M,595,"Litofan iluminat",13,"Medium",WHITE,"right")
    image("relief-unlit-mobile",M,624,118,83)
    tx(158,656,"Lumină stinsă",19,"Serif",WHITE)
    tx(158,680,"Vizualizare de concept",11.5,"Sans",PALE)
    foot(3,True)

    head(4);kicker("Cum funcționează",101)
    tx(M,164,"În trei pași,",52,"Serif");tx(M,211,"acasă la tine.",52,"Serif")
    for i,node in enumerate(home.xpath(classes('tl-steps')+'/li')):
        y=254+i*150
        rule(M,y,W-M,y,LIGHT)
        tx(M,y+35,f"0{i+1}",14,"Medium",NUT)
        tx(M+43,y+39,get(node,"./h3"),30,"Serif")
        para(M+43,y+67,get(node,"./p"),W-2*M-43,15,22)
    button(M,674,"Vezi Luna","luna",120)
    foot(4)

    head(5);kicker("Idei de cadou",101)
    tx(M,164,"Pentru cine",52,"Serif");tx(M,211,"aprinzi Luna?",52,"Italic",NUT)
    y=251
    for i,node in enumerate(home.xpath(classes("tl-gift-options")+"/details")):
        rule(M,y,W-M,y,LIGHT)
        tx(M,y+32,get(node,"./summary"),26,"Serif")
        if i==0:
            tx(W-M,y+30,"−",18,"Sans",NUT,"right")
            y=para(M,y+60,get(node,"./p"),W-2*M,15,21)+13
        else:
            tx(W-M,y+30,"+",18,"Sans",NUT,"right");y+=48
    box(0,610,W,118,INK);tx(M,651,"Dă lumină unei amintiri.",33,"Serif",WHITE)
    button(M,667,"Creează-ți Luna","luna",155,False)
    foot(5)

    head(6)
    tx(M,91,"Acasă   /   "+get(product,"//h1"),13,"Sans",MUTED)
    image("hero",M,109,W-2*M,264,focus=(1,.5))
    c.setFillColor(HexColor(PAPER));c.circle(W-M-21,H-353,12,fill=1,stroke=0);icon("zoom",W-M-21,353,INK,.85)
    for i,key in enumerate(("hero-mobile","feature-mobile","detail-mobile")):
        x=M+i*59
        image(key,x,386,51,42)
        if i==0:rule(x,433,x+51,433,NUT,2)
    para(M,456,field(product,"lp-caption"),W-2*M,11.8,16)
    kicker(field(product,"lp-kicker"),500)
    tx(M,557,get(product,"//h1"),49,"Serif")
    para(M,593,field(product,"lp-description"),W-2*M)
    tx(M,659,field(product,"lp-price"),26,"Medium")
    button(M,680,"Personalizează Luna","personalizare",180)
    foot(6)

    head(7);kicker(get(product,"//h1"),97)
    tx(M,152,"Personalizează Luna",39,"Serif")
    tx(M,187,"Fotografia ta",15,"Medium")
    c.saveState();c.setDash([4,3]);box(M,202,W-2*M,74,"#F1ECE3","#9B8B77",4);c.restoreState()
    icon("photo",M+29,239,NUT,1.15)
    tx(M+55,236,get(product,classes("lp-drop-copy")+"/strong"),15,"Medium")
    tx(M+55,257,get(product,classes("lp-drop-copy")+"/small"),13,"Sans",MUTED)
    para(M,300,get(product,"//*[@id='lp-photo-tip']"),W-2*M,13.5,19)
    tx(M,362,"Observații (opțional)",15,"Medium")
    box(M,375,W-2*M,59,WHITE,"#C3B9AA",4)
    tx(M+12,403,get(product,"//*[@id='lp-notes']/@placeholder"),13.5,"Sans",MUTED)
    box(M,450,100,47,PAPER,"#C3B9AA",4)
    for pos,txt in [(M+16,"−"),(M+50,"1"),(M+84,"+")]:
        tx(pos,480,txt,17,"Medium",MUTED,"center")
    box(M+109,450,W-2*M-109,47,INK,r=4);icon("bag",M+156,473,WHITE,.82);tx(M+178,480,"Adaugă în coș",15,"Medium",WHITE)
    para(M,522,get(product,classes("lp-personalize")+"//p[contains(text(),'Previzualizare offline')]"),W-2*M,13.5,19)
    rule(M,575,W-M,575,LIGHT)
    tx(M,610,"Detalii de design",27,"Serif")
    for i,node in enumerate(product.xpath(classes("lp-specs")+"/div")):
        y=639+i*24
        tx(M,y,get(node,"./dt"),13.5,"Sans",MUTED)
        tx(W-M,y,get(node,"./dd"),13.5,"Medium",INK,"right")
    foot(7)
    c.save()
print(PDF)
