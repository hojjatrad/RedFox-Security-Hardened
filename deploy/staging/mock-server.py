#!/usr/bin/env python3
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json,time,urllib.parse
class H(BaseHTTPRequestHandler):
 def log_message(self,fmt,*args): print(time.strftime('%H:%M:%S'),fmt%args,flush=True)
 def body(self):
  n=int(self.headers.get('content-length','0') or 0);return self.rfile.read(n) if n else b''
 def sendj(self,obj,code=200):
  raw=json.dumps(obj).encode();self.send_response(code);self.send_header('content-type','application/json');self.send_header('content-length',str(len(raw)));self.end_headers();self.wfile.write(raw)
 def do_GET(self):
  p=urllib.parse.urlparse(self.path).path
  if '/getMe' in p:self.sendj({'ok':True,'result':{'id':123456789,'is_bot':True,'username':'redfox_staging_bot'}})
  elif p.startswith('/api/user/') or p.startswith('/api/users/'):
   name=p.rsplit('/',1)[-1];self.sendj({'username':name,'status':'active','enabled':True,'data_limit':10737418240,'used_traffic':1073741824,'expire':int(time.time())+2592000,'subscription_url':'https://staging.invalid/sub/'+name,'links':['vless://mock']})
  elif p=='/health':self.sendj({'ok':True})
  else:self.sendj({'ok':True,'result':{}})
 def do_POST(self):
  p=urllib.parse.urlparse(self.path).path;self.body()
  if p.endswith('/api/admin/token'):self.sendj({'access_token':'mock-panel-token','token_type':'bearer'})
  elif '/sendMessage' in p:self.sendj({'ok':True,'result':{'message_id':int(time.time()),'date':int(time.time())}})
  elif '/setWebhook' in p or '/deleteWebhook' in p:self.sendj({'ok':True,'result':True})
  elif 'verify' in p:self.sendj({'success':True,'status':100,'data':{'status':'approved','message':'Verified','ref_id':'mock-ref'}})
  else:self.sendj({'ok':True,'status':True,'result':{}})
 def do_PUT(self): self.body();self.sendj({'status':True,'ok':True})
 def do_PATCH(self): self.do_PUT()
 def do_DELETE(self): self.do_PUT()
ThreadingHTTPServer(('0.0.0.0',8090),H).serve_forever()
