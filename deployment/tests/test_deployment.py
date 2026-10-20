import base64, datetime as dt, io, json, os, stat, subprocess, sys, tempfile, unittest, zipfile
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from theme_release import *
from easywp import *
from github_gate import verify_source,verify_environment,positive
from sftp_fixture import Server

COMMIT='a'*40
ROOT='/wp-content/themes/'+SLUG
BEFORE={'functions.php':(b'<?php /* old */',0o644),'style.css':(b'/*\nVersion: 1.0.0\n*/',0o644),'custom-live.txt':(b'unknown user file',0o640)}
DESIRED={'functions.php':(b'<?php /* next */',0o644),'style.css':(b'/*\nVersion: 1.0.1\n*/',0o644),'inc/feature.php':(b'<?php /* new */',0o644)}

def baseline(before=BEFORE,desired=DESIRED):
    return {'schema':1,'status':'reconciled','snapshot_reference':'synthetic-only','files':describe(before),'digest':digest(describe(before)),'approved_commit':COMMIT,'approved_zip_sha256':'b'*64,'approved_replacements':changes(describe(before),describe(desired))}

def plan(before=BEFORE):
    return deploy_plan({'theme_root':ROOT},baseline(),before,{'source_commit':COMMIT,'zip_sha256':'b'*64},DESIRED,digest(describe(before)))

class PolicyTests(unittest.TestCase):
    def test_initial_policy_cannot_connect_or_write(self):
        with self.assertRaises(Refused):config_file('.github/deployment/production.json',True)
    def test_paths_reject_escape_and_non_theme_targets(self):
        for p in ['../wp-config.php','/wp-config.php','inc/../functions.php','inc//a.php','inc/a\\b.php','uploads/a.png','plugins/plugin.php','.env','inc/\x00.php']:
            with self.subTest(p=p),self.assertRaises(Refused):relative(p,True)
        self.assertEqual('inc/safe.php',relative('inc/safe.php',True))
    def test_host_backup_confirmation_is_recent_explicit_and_recorded(self):
        now=dt.datetime(2026,10,20,12,tzinfo=dt.timezone.utc)
        record=confirm_backup('dashboard-backup-fixture','2026-10-20T11:00:00Z','CONFIRM CURRENT EASYWP BACKUP AND FROZEN THEME EDITS',now)
        self.assertEqual('dashboard-backup-fixture',record['reference'])
        for stamp,confirmation in [('2026-10-18T11:00:00Z','CONFIRM CURRENT EASYWP BACKUP AND FROZEN THEME EDITS'),('2026-10-21T11:00:00Z','CONFIRM CURRENT EASYWP BACKUP AND FROZEN THEME EDITS'),('2026-10-20T11:00:00Z','yes')]:
            with self.assertRaises(Refused):confirm_backup('fixture',stamp,confirmation,now)
    def test_unreconciled_drift_and_unapproved_replacement_fail_closed(self):
        for key,value in [('status','unreconciled'),('approved_commit','f'*40),('approved_zip_sha256','e'*64),('approved_replacements',{})]:
            b=baseline();b[key]=value
            with self.assertRaises(Refused):deploy_plan({'theme_root':ROOT},b,BEFORE,{'source_commit':COMMIT,'zip_sha256':'b'*64},DESIRED,digest(describe(BEFORE)))
        modified=dict(BEFORE);modified['custom-live.txt']=(b'new user edit',0o640)
        with self.assertRaises(Refused):plan(modified)
    def test_no_normal_delete_and_no_version_downgrade(self):
        p=plan();self.assertNotIn('custom-live.txt',p['changes']);self.assertEqual([],p['remove'])
        old=dict(DESIRED);old['style.css']=BEFORE['style.css']
        b=baseline(desired=old)
        with self.assertRaises(Refused):deploy_plan({'theme_root':ROOT},b,BEFORE,{'source_commit':COMMIT,'zip_sha256':'b'*64},old,digest(describe(BEFORE)))
    def test_encrypted_backup_authentication_and_recovery(self):
        key=b'k'*32;cipher=seal(BEFORE,plan(),key)
        self.assertNotIn(b'unknown user file',cipher)
        restored,files=unseal(cipher,key);self.assertEqual(BEFORE,files);self.assertEqual(plan(),restored)
        with self.assertRaises(Exception):unseal(cipher[:-1]+bytes([cipher[-1]^1]),key)
        with self.assertRaises(Exception):unseal(cipher,b'j'*32)
        with self.assertRaises(Refused):key_bytes(base64.b64encode(b'short').decode())
    def test_rollback_will_not_overwrite_later_managed_edits(self):
        original=plan();current=dict(BEFORE);current.update(DESIRED)
        current['functions.php']=(b'new human edit',0o644)
        with self.assertRaises(Refused):rollback_plan({'theme_root':ROOT},original,BEFORE,current,digest(describe(current)),COMMIT)
    def test_input_identifiers_are_not_shell_or_paths(self):
        for v in ['1; ls','../3','$(whoami)','0','-1','a','1\n']:
            with self.assertRaises(Refused):positive(v)
    def test_environment_requires_exact_main_and_reviewers(self):
        def get(p):
            if 'deployment-branch-policies?' in p:return {'total_count':1,'branch_policies':[{'name':'main','type':'branch'}]}
            return {'deployment_branch_policy':{'custom_branch_policies':True},'protection_rules':[{'type':'required_reviewers','reviewers':[{'id':1}]}]}
        verify_environment({'approval_mode':'environment-reviewers'},get)
        def no_review(p):
            d=get(p);d['protection_rules']=[];return d
        with self.assertRaises(Refused):verify_environment({'approval_mode':'environment-reviewers'},no_review)
    def test_source_only_accepts_successful_main_test_build(self):
        responses={
          '/repos/schwegler/tacobout.online/actions/runs/123':{'repository':{'full_name':'schwegler/tacobout.online'},'path':'.github/workflows/tests.yml','head_branch':'main','event':'push','head_sha':COMMIT},
          '/repos/schwegler/tacobout.online/actions/runs/123/attempts/1':{'status':'completed','conclusion':'success','head_sha':COMMIT},
          '/repos/schwegler/tacobout.online/actions/runs/123/artifacts?per_page=100':{'total_count':1,'artifacts':[{'id':99,'name':'tested-theme-'+COMMIT+'-1','expired':False,'size_in_bytes':100,'digest':'sha256:'+'c'*64}]}}
        self.assertEqual('99',verify_source('deploy','123','1',COMMIT,responses.__getitem__)['artifact_id'])
        responses['/repos/schwegler/tacobout.online/actions/runs/123']['event']='pull_request'
        with self.assertRaises(Refused):verify_source('deploy','123','1',COMMIT,responses.__getitem__)

    def test_http_smoke_rejects_fatal_pages_cached_html_and_changed_assets(self):
        style=b'body{}';target='style.'+sha(style)[:16]+'.css'
        desired={'style.css':(style,0o644),target:(style,0o644),'asset-manifest.json':(canonical({'style.css':target}),0o644)}
        c={'site_url':'https://tacobout.online','public_theme_url':'https://tacobout.online/wp-content/themes/'+SLUG,'smoke_paths':['/','/author/schwegler/']}
        with patch('easywp.http_read',side_effect=[target.encode(),target.encode(),style]):smoke(c,desired)
        for bodies in [[b'Fatal error: hidden PHP detail'],[b'old style',b'old style'],[target.encode(),target.encode(),b'wrong asset']]:
            with patch('easywp.http_read',side_effect=bodies),self.assertRaises(Refused):smoke(c,desired)
    def test_http_redirect_cannot_leave_the_verified_https_origin(self):
        from urllib.request import Request
        redirect=SameOriginRedirect()
        for url in ['http://tacobout.online/','https://elsewhere.example/']:
            with self.assertRaises(Refused):redirect.redirect_request(Request('https://tacobout.online/'),None,302,'',{},url)

class ArtifactTests(unittest.TestCase):
    def test_real_repository_build_load_and_repeatability(self):
        repo=Path(__file__).resolve().parents[2]
        with tempfile.TemporaryDirectory() as temp:
            first=Path(temp)/'first';second=Path(temp)/'second'
            manifest=build(repo,first);again=build(repo,second)
            self.assertEqual(manifest,again)
            self.assertEqual((first/'theme.zip').read_bytes(),(second/'theme.zip').read_bytes())
            _,files=load(first,manifest['source_commit'],manifest['zip_sha256'])
            self.assertIn('asset-manifest.json',files)
            self.assertIn('templates/author.html',files)
            self.assertFalse(any(p.startswith(('deployment/','.github/','tests/')) for p in files))
    def test_exact_git_build_ignores_untracked_and_dev_files_and_checks_tamper(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);repo=root/'repo';repo.mkdir()
            for p,data in {'style.css':b'/*\nVersion: 1.0.0\n*/','style.copy.css':b'/*\nVersion: 1.0.0\n*/','functions.php':b'<?php','theme.json':b'{}','asset-manifest.json':b'{"style.css":"style.copy.css"}','templates/author.html':b'author','tests/not-runtime.txt':b'no','deployment/no.py':b'no'}.items():
                target=repo/p;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
            subprocess.run(['git','init','-q',str(repo)],check=True)
            subprocess.run(['git','-C',str(repo),'add','.'],check=True)
            subprocess.run(['git','-C',str(repo),'-c','user.name=Fixture','-c','user.email=fixture@example.test','commit','-qm','fixture'],check=True)
            style=(repo/'style.css').read_bytes();target='style.'+sha(style)[:16]+'.css'
            (repo/'style.copy.css').rename(repo/target)
            (repo/'asset-manifest.json').write_bytes(canonical({'style.css':target}))
            subprocess.run(['git','-C',str(repo),'add','.'],check=True)
            subprocess.run(['git','-C',str(repo),'-c','user.name=Fixture','-c','user.email=fixture@example.test','commit','-qm','fingerprinted'],check=True)
            (repo/'secret-untracked.js').write_bytes(b'must not ship')
            m=build(repo,root/'out');_,files=load(root/'out',m['source_commit'],m['zip_sha256'])
            self.assertNotIn('secret-untracked.js',files);self.assertNotIn('tests/not-runtime.txt',files);self.assertNotIn('deployment/no.py',files)
            with self.assertRaises(Refused):load(root/'out','f'*40,m['zip_sha256'])
            (root/'out/theme.zip').write_bytes(b'changed')
            with self.assertRaises(Refused):load(root/'out',m['source_commit'],m['zip_sha256'])
    def test_zip_traversal_and_symlink_fail_even_with_matching_outer_digest(self):
        for name,mode in [(SLUG+'/../wp-config.php',0o100644),(SLUG+'/functions.php',0o120777)]:
            with tempfile.TemporaryDirectory() as d:
                data=io.BytesIO()
                with zipfile.ZipFile(data,'w') as z:
                    i=zipfile.ZipInfo(name);i.external_attr=mode<<16;z.writestr(i,b'unsafe')
                raw=data.getvalue();Path(d,'theme.zip').write_bytes(raw)
                Path(d,'release.json').write_bytes(canonical({'schema':1,'theme_slug':SLUG,'source_commit':COMMIT,'zip_sha256':sha(raw),'files':{}}))
                with self.assertRaises(Refused):load(d,COMMIT,sha(raw))

class SFTPTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.base=Path(self.temp.name);self.target=self.base/ROOT.lstrip('/');self.target.mkdir(parents=True)
        for name,(data,mode) in BEFORE.items():p=self.target/name;p.write_bytes(data);p.chmod(mode)
        (self.base/'wp-config.php').write_bytes(b'never read')
        self.server=Server(self.base)
    def tearDown(self):self.server.close();self.temp.cleanup()
    def connect(self,known=None):return connection('127.0.0.1',self.server.port,'fixture','synthetic-only',self.server.known_hosts if known is None else known)
    def receipt(self,p):
        cipher=seal(BEFORE,p,b'k'*32)
        return {'plan_sha256':sha(canonical(p)),'cipher_sha256':sha(cipher),'artifact_id':'12'},cipher
    def test_strict_host_key_rejects_missing_or_wrong_pin(self):
        with self.assertRaises(Exception):
            with self.connect('example.test ssh-rsa AAAA\n'):pass
        wrong=paramiko.RSAKey.generate(2048)
        hosts=f'[127.0.0.1]:{self.server.port} {wrong.get_name()} {wrong.get_base64()}\n'
        with self.assertRaises(paramiko.BadHostKeyException):
            with self.connect(hosts):pass
        self.assertEqual([],self.server.requests);self.assertEqual([],self.server.auth_attempts)
    def test_snapshot_deploy_and_explicit_rollback_preserve_unknown_files(self):
        with self.connect() as sftp:
            remote=ThemeRemote(sftp,ROOT);self.assertEqual(BEFORE,remote.snapshot())
            p=plan();r,cipher=self.receipt(p);audit=self.base/'audit.json'
            apply(remote,p,DESIRED,r,cipher,audit)
            after=remote.snapshot();self.assertEqual(BEFORE['custom-live.txt'],after['custom-live.txt'])
            self.assertEqual(DESIRED['functions.php'],after['functions.php'])
            rollback,desired=rollback_plan({'theme_root':ROOT},p,BEFORE,after,digest(describe(after)),COMMIT)
            backup=seal(after,rollback,b'k'*32);receipt={'plan_sha256':sha(canonical(rollback)),'cipher_sha256':sha(backup),'artifact_id':'13'}
            apply(remote,rollback,desired,receipt,backup,audit)
            self.assertEqual(BEFORE,remote.snapshot())
        self.assertNotIn('/wp-config.php',self.server.requests)
    def test_unpersisted_backup_or_drift_stops_all_writes(self):
        with self.connect() as sftp:
            remote=ThemeRemote(sftp,ROOT);p=plan();r,cipher=self.receipt(p);r['artifact_id']=''
            with self.assertRaises(Refused):apply(remote,p,DESIRED,r,cipher,self.base/'audit.json')
            self.assertEqual(BEFORE,remote.snapshot())
            r['artifact_id']='12';(self.target/'custom-live.txt').write_bytes(b'new user edit')
            with self.assertRaises(Refused):apply(remote,p,DESIRED,r,cipher,self.base/'audit.json')
            self.assertEqual(BEFORE['functions.php'][0],(self.target/'functions.php').read_bytes())
    def test_unsupported_per_file_replace_has_no_managed_mutations(self):
        self.server.no_replace=True
        with self.connect() as sftp:
            remote=ThemeRemote(sftp,ROOT);p=plan();r,cipher=self.receipt(p)
            with self.assertRaises(OSError):apply(remote,p,DESIRED,r,cipher,self.base/'audit.json')
            self.assertEqual(BEFORE,remote.snapshot())
    def test_partial_failure_is_recoverable_without_unknown_file_loss(self):
        self.server.fail_rename_at=3  # probe succeeds; first managed replacement succeeds.
        with self.connect() as sftp:
            remote=ThemeRemote(sftp,ROOT);p=plan();r,cipher=self.receipt(p);audit=self.base/'audit.json'
            with self.assertRaises(OSError):apply(remote,p,DESIRED,r,cipher,audit)
            partial=remote.snapshot();self.assertNotEqual(BEFORE,partial)
            self.assertEqual(BEFORE['custom-live.txt'],partial['custom-live.txt'])
            self.server.fail_rename_at=None
            rollback,desired=rollback_plan({'theme_root':ROOT},p,BEFORE,partial,digest(describe(partial)),COMMIT)
            backup=seal(partial,rollback,b'k'*32);receipt={'plan_sha256':sha(canonical(rollback)),'cipher_sha256':sha(backup),'artifact_id':'13'}
            apply(remote,rollback,desired,receipt,backup,audit)
            self.assertEqual(BEFORE,remote.snapshot())
    def test_rollback_preserves_new_unmanaged_edits(self):
        with self.connect() as sftp:
            remote=ThemeRemote(sftp,ROOT);p=plan();r,cipher=self.receipt(p);audit=self.base/'audit.json'
            apply(remote,p,DESIRED,r,cipher,audit)
            (self.target/'custom-live.txt').write_bytes(b'human update after release')
            current=remote.snapshot()
            rollback,desired=rollback_plan({'theme_root':ROOT},p,BEFORE,current,digest(describe(current)),COMMIT)
            backup=seal(current,rollback,b'k'*32);receipt={'plan_sha256':sha(canonical(rollback)),'cipher_sha256':sha(backup),'artifact_id':'13'}
            apply(remote,rollback,desired,receipt,backup,audit)
            expected=dict(BEFORE);expected['custom-live.txt']=(b'human update after release',0o640)
            self.assertEqual(expected,remote.snapshot())
    def test_symlink_in_theme_or_ancestry_is_never_followed(self):
        (self.target/'inc').symlink_to(self.base,target_is_directory=True)
        with self.connect() as sftp:
            with self.assertRaises(Refused):ThemeRemote(sftp,ROOT).snapshot()
        (self.target/'inc').unlink();real=self.target.with_name('actual');self.target.rename(real);self.target.symlink_to(real,target_is_directory=True)
        with self.connect() as sftp:
            with self.assertRaises(Refused):ThemeRemote(sftp,ROOT)

if __name__=='__main__':unittest.main()
