import io, json, subprocess, sys, tempfile, unittest, zipfile
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from theme_release import *
COMMIT="a"*40

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


    def test_development_payload_paths_are_refused(self):
        for path in ['assets/a.js.map','assets/node_modules/a.js','inc/tests/fixture.php','assets/.env','assets/design.psd']:
            with self.subTest(path=path), self.assertRaises(Refused):
                relative(path, True)

    def test_release_metadata_matches_verified_archive_and_tag(self):
        from update_metadata import generate
        repo=Path(__file__).resolve().parents[2]
        with tempfile.TemporaryDirectory() as temp:
            build(repo,temp)
            number='.'.join(map(str,version((repo/'style.css').read_bytes())))
            metadata=generate(temp,'v'+number)
            self.assertEqual(SLUG,metadata['theme'])
            self.assertTrue(metadata['package'].endswith('/v'+number+'/theme.zip'))
            with self.assertRaises(Refused):generate(temp,'v99.0.0')

    def test_historical_reconciliation_evidence_remains_exact(self):
        root=Path(__file__).resolve().parents[2]
        evidence=json.loads((root/'.github/deployment/live-snapshot-content.json').read_bytes())
        review=json.loads((root/'.github/deployment/release-content-review.json').read_bytes())
        baseline=json.loads((root/'.github/deployment/production-baseline.json').read_bytes())
        reviewed=json.loads((root/'deployment/tests/fixtures/reviewed-3.8.25-release.json').read_bytes())
        self.assertEqual(212,len(evidence['files']))
        self.assertFalse(evidence['permissions_verified'])
        self.assertEqual('unreconciled',baseline['status'])
        self.assertIsNone(baseline['files'])
        self.assertIsNone(baseline['approved_commit'])
        self.assertEqual(review['candidate_zip_sha256'],reviewed['zip_sha256'])
        self.assertEqual(review['runtime_file_count'],len(reviewed['files']))
        changes={p: {'from': evidence['files'].get(p, {}).get('sha256'), 'to': meta['sha256']}
                 for p,meta in sorted(reviewed['files'].items())
                 if evidence['files'].get(p, {}).get('sha256') != meta['sha256']}
        self.assertEqual(review['changes'],changes)
        self.assertTrue(all(p in evidence['files'] and p not in reviewed['files'] for p in review['preserved_snapshot_files']))
