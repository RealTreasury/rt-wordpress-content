"""Exercise the Guide automation plan and read-back without a Resend account."""

import json
import subprocess
import sys
import tempfile
import unittest
from copy import deepcopy
from pathlib import Path

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))
import resend_guide_feedback_graph as graph


def baseline():
    return {
        'id': graph.GUIDE_AUTOMATION,
        'status': 'enabled',
        'steps': [
            {'key': 'trigger', 'type': 'trigger', 'config': {'eventName': 'signup.created'}},
            {'key': 'is_guide_download', 'type': 'condition', 'config': {'segment': 'guide-only'}},
            {'key': 'send_guide', 'type': 'send_email',
             'config': {'from': 'old@example.com', 'template': {'id': 'original-delivery'}}},
        ],
        'connections': [
            {'from': 'trigger', 'to': 'is_guide_download'},
            {'from': 'is_guide_download', 'to': 'send_guide', 'type': 'yes'},
        ],
    }


class GuideFeedbackGraphTests(unittest.TestCase):
    def setUp(self):
        self.before = baseline()
        self.after = {**graph.build(self.before), 'id': graph.GUIDE_AUTOMATION, 'status': 'enabled'}

    def test_build_preserves_delivery_and_adds_feedback(self):
        self.assertEqual(self.before, baseline())
        self.assertEqual(self.after['steps'][1], self.before['steps'][1])
        delivery = self.after['steps'][2]['config']
        self.assertEqual(delivery['template'], {'id': 'original-delivery'})
        self.assertEqual(delivery['from'], graph.SENDER)
        self.assertEqual(delivery['reply_to'], graph.REPLY_TO)
        self.assertEqual(self.after['steps'][3]['config']['duration'], '7 days')
        self.assertEqual(self.after['steps'][4]['config']['template'], {'id': graph.FEEDBACK_TEMPLATE})

    def test_verify_uses_topology_and_settings_instead_of_response_order(self):
        self.after['steps'].reverse()
        self.after['connections'].reverse()
        self.after['name'] = 'Tech Selection Guide'
        graph.verify(self.before, self.after)

    def test_verify_refuses_disabled_incomplete_or_changed_rollouts(self):
        variants = (
            {**self.after, 'status': 'disabled'},
            {**self.after, 'id': 'different-automation'},
            {**self.after, 'steps': self.before['steps']},
            {**self.after, 'connections': self.after['connections'][:-1]},
        )
        for variant in variants:
            with self.subTest(variant=variant), self.assertRaises(ValueError):
                graph.verify(self.before, variant)

        wrong_filter = deepcopy(self.after)
        wrong_filter['steps'][1]['config']['segment'] = 'everyone'
        with self.assertRaisesRegex(ValueError, 'step types or settings'):
            graph.verify(self.before, wrong_filter)

    def test_verify_cli_does_not_write_the_readback(self):
        with tempfile.TemporaryDirectory() as temp:
            before_path = Path(temp) / 'before.json'
            after_path = Path(temp) / 'after.json'
            before_path.write_text(json.dumps(self.before), encoding='utf-8')
            after_json = json.dumps(self.after)
            after_path.write_text(after_json, encoding='utf-8')
            result = subprocess.run(
                [sys.executable, str(SCRIPTS / 'resend_guide_feedback_graph.py'),
                 '--verify', str(before_path), str(after_path)],
                capture_output=True, text=True,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(after_path.read_text(encoding='utf-8'), after_json)

    def test_verify_accepts_extra_readback_settings_but_not_changed_ones(self):
        extra = deepcopy(self.after)
        extra['steps'][2]['config']['template']['version'] = 3
        extra['steps'][3]['config']['timezone'] = 'UTC'
        graph.verify(self.before, extra)
        changed = deepcopy(self.after)
        changed['steps'][3]['config']['duration'] = '1 day'
        with self.assertRaisesRegex(ValueError, 'step types or settings'):
            graph.verify(self.before, changed)
        missing = deepcopy(self.after)
        del missing['steps'][2]['config']['reply_to']
        with self.assertRaisesRegex(ValueError, 'step types or settings'):
            graph.verify(self.before, missing)

    def run_cli(self, *args):
        return subprocess.run(
            [sys.executable, str(SCRIPTS / 'resend_guide_feedback_graph.py'), *map(str, args)],
            capture_output=True, text=True,
        )

    def test_build_cli_writes_a_patch_body_that_verifies(self):
        with tempfile.TemporaryDirectory() as temp:
            before_path = Path(temp) / 'before.json'
            patch_path = Path(temp) / 'patch.json'
            before_path.write_text(json.dumps(self.before), encoding='utf-8')
            result = self.run_cli(before_path, patch_path)
            self.assertEqual(result.returncode, 0, result.stderr)
            patch = json.loads(patch_path.read_text(encoding='utf-8'))
            self.assertEqual(patch, graph.build(self.before))
            graph.verify(self.before, {**patch, 'id': graph.GUIDE_AUTOMATION, 'status': 'enabled'})

    def test_template_readback_requires_verify(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / 'x.json'
            path.write_text('{}', encoding='utf-8')
            result = self.run_cli('--template-readback', path, path, path)
            self.assertEqual(result.returncode, 2)
            self.assertIn('--template-readback requires --verify', result.stderr)

    def test_verify_cli_checks_the_template_readback(self):
        feedback = SCRIPTS.parent / 'treasury-tech-selection' / 'guidebook' / 'feedback'
        template = {
            'id': graph.FEEDBACK_TEMPLATE, 'status': 'published',
            'published_at': '2026-11-19T00:00:00Z',
            'html': (feedback / 'resend-guide-feedback.html').read_text(encoding='utf-8'),
            'text': (feedback / 'resend-guide-feedback.txt').read_text(encoding='utf-8'),
        }
        with tempfile.TemporaryDirectory() as temp:
            before_path = Path(temp) / 'before.json'
            after_path = Path(temp) / 'after.json'
            template_path = Path(temp) / 'template.json'
            before_path.write_text(json.dumps(self.before), encoding='utf-8')
            after_path.write_text(json.dumps(self.after), encoding='utf-8')
            template_path.write_text(json.dumps(template), encoding='utf-8')
            ok = self.run_cli('--verify', '--template-readback', template_path, before_path, after_path)
            self.assertEqual(ok.returncode, 0, ok.stderr)
            self.assertIn('five star links', ok.stdout)
            template_path.write_text(json.dumps({**template, 'status': 'draft'}), encoding='utf-8')
            bad = self.run_cli('--verify', '--template-readback', template_path, before_path, after_path)
            self.assertEqual(bad.returncode, 1)
            self.assertIn('not published', bad.stderr)

    def test_template_needs_publication_and_links_in_both_parts(self):
        feedback = SCRIPTS.parent / 'treasury-tech-selection' / 'guidebook' / 'feedback'
        template = {
            'id': graph.FEEDBACK_TEMPLATE,
            'status': 'published',
            'published_at': '2026-11-19T00:00:00Z',
            'html': (feedback / 'resend-guide-feedback.html').read_text(encoding='utf-8'),
            'text': (feedback / 'resend-guide-feedback.txt').read_text(encoding='utf-8'),
        }
        graph.verify_template(template)
        with self.assertRaisesRegex(ValueError, 'not published'):
            graph.verify_template({**template, 'status': 'draft', 'published_at': None})
        missing_star = template['text'].replace(f'{graph.RATING_URL}5', f'{graph.RATING_URL}50')
        with self.assertRaisesRegex(ValueError, 'text lacks'):
            graph.verify_template({**template, 'text': missing_star})
        with self.assertRaisesRegex(ValueError, 'html lacks'):
            graph.verify_template({**template, 'html': template['html'].replace('href=', 'data-link=')})


if __name__ == '__main__':
    unittest.main()
