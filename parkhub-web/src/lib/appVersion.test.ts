import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import pkg from '../../package.json' with { type: 'json' };

const repoRoot = resolve(process.cwd(), '..');

describe('app version drift', () => {
  it('parkhub-web/package.json matches /VERSION', () => {
    const v = readFileSync(`${repoRoot}/VERSION`, 'utf8').trim();
    expect(pkg.version).toBe(v);
  });

  it('helm Chart appVersion matches /VERSION', () => {
    const v = readFileSync(`${repoRoot}/VERSION`, 'utf8').trim();
    const chart = readFileSync(`${repoRoot}/helm/parkhub/Chart.yaml`, 'utf8');
    const m = chart.match(/^appVersion:\s*"?([^"\n]+)"?/m);
    expect(m?.[1]).toBe(v);
  });
});
