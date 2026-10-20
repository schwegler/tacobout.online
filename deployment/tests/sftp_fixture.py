"""Loopback-only disposable SFTP server; rejects shell/exec. Not production credentials."""
import errno, os, posixpath, socket, threading
from pathlib import Path
import paramiko

class Policy(paramiko.ServerInterface):
    def __init__(self,fixture):self.fixture=fixture
    def check_auth_password(self, username, password):
        self.fixture.auth_attempts.append(username)
        return paramiko.AUTH_SUCCESSFUL if (username, password) == ('fixture', 'synthetic-only') else paramiko.AUTH_FAILED
    def get_allowed_auths(self, username): return 'password'
    def check_channel_request(self, kind, chanid):
        return paramiko.OPEN_SUCCEEDED if kind == 'session' else paramiko.OPEN_FAILED_ADMINISTRATIVELY_PROHIBITED
    def check_channel_exec_request(self, channel, command): return False
    def check_channel_shell_request(self, channel): return False

class Files(paramiko.SFTPServerInterface):
    def __init__(self, server, *args, fixture=None, **kwargs):
        super().__init__(server, *args, **kwargs); self.fixture = fixture
    def path(self, path):
        self.fixture.requests.append(path)
        normalized = posixpath.normpath('/' + path.lstrip('/'))
        return self.fixture.base / normalized.lstrip('/')
    def canonicalize(self, path): return posixpath.normpath('/' + path.lstrip('/'))
    def lstat(self, path):
        try: return paramiko.SFTPAttributes.from_stat(os.lstat(self.path(path)))
        except OSError as e: return paramiko.SFTPServer.convert_errno(e.errno)
    def stat(self, path):
        try: return paramiko.SFTPAttributes.from_stat(os.stat(self.path(path)))
        except OSError as e: return paramiko.SFTPServer.convert_errno(e.errno)
    def list_folder(self, path):
        try:
            result=[]
            for p in sorted(self.path(path).iterdir()):
                a=paramiko.SFTPAttributes.from_stat(p.lstat());a.filename=p.name;result.append(a)
            return result
        except OSError as e: return paramiko.SFTPServer.convert_errno(e.errno)
    def open(self, path, flags, attr):
        try:
            fd=os.open(self.path(path), flags, attr.st_mode if attr.st_mode is not None else 0o600)
            mode='r+b' if flags & os.O_RDWR else 'wb' if flags & os.O_WRONLY else 'rb'
            f=os.fdopen(fd,mode,buffering=0);h=paramiko.SFTPHandle(flags);h.readfile=f;h.writefile=f
            h.stat=lambda:paramiko.SFTPAttributes.from_stat(os.fstat(f.fileno()))
            def chattr(a):
                if a.st_mode is not None:os.fchmod(f.fileno(),a.st_mode)
                return paramiko.SFTP_OK
            h.chattr=chattr
            return h
        except OSError as e: return paramiko.SFTPServer.convert_errno(e.errno)
    def chattr(self,path,attr):
        try:
            if attr.st_mode is not None:os.chmod(self.path(path),attr.st_mode)
            return paramiko.SFTP_OK
        except OSError as e:return paramiko.SFTPServer.convert_errno(e.errno)
    def mkdir(self,path,attr):
        try:self.path(path).mkdir(mode=attr.st_mode or 0o755);return paramiko.SFTP_OK
        except OSError as e:return paramiko.SFTPServer.convert_errno(e.errno)
    def remove(self,path):
        try:self.path(path).unlink();return paramiko.SFTP_OK
        except OSError as e:return paramiko.SFTPServer.convert_errno(e.errno)
    def posix_rename(self,old,new):
        if self.fixture.no_replace:return paramiko.SFTP_OP_UNSUPPORTED
        try:
            self.fixture.renames += 1
            if self.fixture.fail_rename_at == self.fixture.renames:return paramiko.SFTP_FAILURE
            os.replace(self.path(old),self.path(new));return paramiko.SFTP_OK
        except OSError as e:return paramiko.SFTPServer.convert_errno(e.errno)

class Server:
    def __init__(self,base):
        self.base=Path(base);self.requests=[];self.no_replace=False;self.auth_attempts=[];self.renames=0;self.fail_rename_at=None
        self.key=paramiko.RSAKey.generate(2048);self.transports=[];self.stop=threading.Event()
        self.sock=socket.socket();self.sock.bind(('127.0.0.1',0));self.sock.listen();self.sock.settimeout(.2)
        self.port=self.sock.getsockname()[1]
        self.known_hosts=f'[127.0.0.1]:{self.port} {self.key.get_name()} {self.key.get_base64()}\n'
        self.thread=threading.Thread(target=self.serve,daemon=True);self.thread.start()
    def serve(self):
        while not self.stop.is_set():
            try:c,_=self.sock.accept()
            except socket.timeout:continue
            except OSError:break
            t=paramiko.Transport(c);t.add_server_key(self.key);self.transports.append(t)
            t.set_subsystem_handler('sftp',paramiko.SFTPServer,Files,fixture=self)
            try:t.start_server(server=Policy(self))
            except (EOFError,paramiko.SSHException):t.close()
    def close(self):
        self.stop.set();self.sock.close()
        for t in self.transports:t.close()
        self.thread.join(timeout=2)
