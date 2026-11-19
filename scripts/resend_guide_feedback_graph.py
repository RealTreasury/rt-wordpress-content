#!/usr/bin/env python3
"""Build a reviewed Guide feedback graph or verify a saved Resend read-back.

Both modes use local JSON files and make no network requests.
"""
import argparse
import json
import re
from collections import Counter
from copy import deepcopy
from html.parser import HTMLParser
from pathlib import Path

SENDER = 'Real Treasury <guide@news.realtreasury.com>'
REPLY_TO = 'contact@realtreasury.com'
FEEDBACK_TEMPLATE = '04bc56bd-6b94-4d3e-9269-837131e30df3'
GUIDE_AUTOMATION = '01a067af-c041-7579-a1f3-ad0f042f25fe'
RATING_URL = 'https://realtreasury.com/treasury-tech-selection-guide/feedback/?email={{{EMAIL}}}&r='


def build(live):
    if live['status'] != 'enabled':
        raise ValueError('expected the before-change automation to be enabled')
    keys = [s['key'] for s in live['steps']]
    if keys != ['trigger', 'is_guide_download', 'send_guide']:
        raise ValueError('expected the three original Guide delivery steps in the before-change graph')
    steps = deepcopy(live['steps'])
    steps[-1]['config']['from'] = SENDER
    steps[-1]['config']['reply_to'] = REPLY_TO
    steps = steps + [
        {'key': 'wait_7_days', 'type': 'delay', 'config': {'duration': '7 days'}},
        {'key': 'send_feedback', 'type': 'send_email', 'config': {
            'from': SENDER, 'reply_to': REPLY_TO, 'subject': 'Was the guide useful?',
            'template': {'id': FEEDBACK_TEMPLATE}}},
    ]
    connections = live['connections'] + [
        {'from': 'send_guide', 'to': 'wait_7_days', 'type': 'default'},
        {'from': 'wait_7_days', 'to': 'send_feedback', 'type': 'default'},
    ]
    return {'steps': steps, 'connections': connections}


def verify(before, after):
    if before['id'] != GUIDE_AUTOMATION or after['id'] != GUIDE_AUTOMATION:
        raise ValueError('expected both snapshots to be for the Guide delivery automation')
    if after['status'] != 'enabled':
        raise ValueError('the Guide delivery automation is not enabled')
    expected = build(before)
    actual_steps = after['steps']
    planned_steps = expected['steps']
    step_settings = {step['key']: (step['type'], step['config']) for step in actual_steps}
    if len(actual_steps) != len(planned_steps) or step_settings != {
        step['key']: (step['type'], step['config']) for step in planned_steps
    }:
        raise ValueError('live step types or settings differ from the planned Guide graph')

    def edges(graph):
        return Counter((edge['from'], edge['to'], edge.get('type', 'default'))
                       for edge in graph['connections'])

    if edges(after) != edges(expected):
        raise ValueError('live connections differ from the planned Guide graph')


def verify_template(template):
    if (template['id'] != FEEDBACK_TEMPLATE
            or template['status'] != 'published' or not template['published_at']):
        raise ValueError('the Guide feedback template is not published')
    class AnchorLinks(HTMLParser):
        def __init__(self):
            super().__init__()
            self.urls = []

        def handle_starttag(self, tag, attrs):
            if tag == 'a':
                self.urls.extend(value for name, value in attrs if name == 'href' and value)

    anchors = AnchorLinks()
    anchors.feed(template['html'])
    link = re.compile(rf"(?<!\S){re.escape(RATING_URL)}([1-5])(?=$|\s)")
    for field, content in (('html', '\n'.join(anchors.urls)), ('text', template['text'])):
        if set(link.findall(content)) != set('12345'):
            raise ValueError(f'published template {field} lacks one or more Guide star links')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verify', action='store_true', help='verify instead of writing a PATCH body')
    parser.add_argument('--template-readback', type=Path, help='also verify a saved template GET response')
    parser.add_argument('before', type=Path, help='automation GET snapshot taken before the change')
    parser.add_argument('result', type=Path, help='output PATCH body or, with --verify, later GET snapshot')
    args = parser.parse_args()
    if args.template_readback and not args.verify:
        parser.error('--template-readback requires --verify')
    try:
        before = json.loads(args.before.read_text(encoding='utf-8'))
        if args.verify:
            after = json.loads(args.result.read_text(encoding='utf-8'))
            verify(before, after)
            if args.template_readback:
                verify_template(json.loads(args.template_readback.read_text(encoding='utf-8')))
                print('Guide feedback template is published and contains the five star links.')
            print('Guide feedback automation is enabled and matches the planned graph.')
        else:
            args.result.write_text(json.dumps(build(before), indent=2), encoding='utf-8')
    except (OSError, KeyError, TypeError, json.JSONDecodeError):
        parser.exit(1, 'Could not read the expected Guide feedback automation JSON files.\n')
    except ValueError as exc:
        parser.exit(1, f'{exc}\n')


if __name__ == '__main__':
    main()
