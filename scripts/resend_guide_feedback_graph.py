#!/usr/bin/env python3
"""Build the guide-feedback automation graph for the owner to review. Read-only.

Reads the live automation JSON saved by `curl` (path in argv[1]) and writes the new
{"steps", "connections"} body to argv[2]. It makes no Resend call. Applying it is the owner's
step 6 in docs/guide-release-runbook.md.
"""
import json
import sys

SENDER = 'Real Treasury <guide@news.realtreasury.com>'
REPLY_TO = 'contact@realtreasury.com'
FEEDBACK_TEMPLATE = '04bc56bd-6b94-4d3e-9269-837131e30df3'


def build(live):
    assert live['status'] == 'enabled', 'expected the live automation to be enabled'
    keys = [s['key'] for s in live['steps']]
    assert keys == ['trigger', 'is_guide_download', 'send_guide'], 'live graph changed: %s' % keys
    steps = live['steps']
    for s in steps:
        if s['key'] == 'send_guide':
            s['config']['from'] = SENDER
            s['config']['reply_to'] = REPLY_TO
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


if __name__ == '__main__':
    with open(sys.argv[1]) as f:
        body = build(json.load(f))
    with open(sys.argv[2], 'w') as f:
        json.dump(body, f, indent=2)
