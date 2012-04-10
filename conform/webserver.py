#!/usr/bin/env python
from BaseHTTPServer import HTTPServer
from CGIHTTPServer import CGIHTTPRequestHandler
import sys

class MyHandler(CGIHTTPRequestHandler):

    def do_POST(self):
        if self.path == "/":
            self.path = "/cgi-bin/server.php"
        CGIHTTPRequestHandler.do_POST(self)
        
port = 8080
if len(sys.argv) > 1:
    port = int(sys.argv[1])

print "Starting HTTP server on port %d" % port
serve = HTTPServer(("",port),MyHandler)
serve.serve_forever()
