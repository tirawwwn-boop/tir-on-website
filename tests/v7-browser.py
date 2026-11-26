"""Serve a disposable authenticated WordPress fixture on loopback for the screenshot checks."""
from http.server import ThreadingHTTPServer
import os
import runpy
import subprocess
from threading import Thread
from atelier_cgi_fixture import Fixture, ROOT

fixture=Fixture()
try:
    bridge=runpy.run_path(str(ROOT/'tests/v5-browser-server.py'))
    fixture.run_wp('option','update','show_avatars','0')
    identities=fixture.run_wp('eval-file',str(ROOT/'tests/v7-fixtures.php'),'login')
    with ThreadingHTTPServer(('127.0.0.1',4173),bridge['handler'](fixture)) as server:
        Thread(target=server.serve_forever,daemon=True).start()
        try:
            subprocess.run(['node',str(ROOT/'tests/v7-browser.cjs')],input=identities,text=True,check=True,cwd=ROOT,env=os.environ)
        finally:
            server.shutdown()
finally:
    fixture.close()
