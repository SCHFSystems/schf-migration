import { describe, expect, it } from 'vitest';
import { isSyntheticPipelineComplete, syntheticPipelineSteps } from './pipelineSteps';

describe('synthetic pipeline steps', () => {
  it('contains the Sprint 9 synthetic flow', () => {
    expect(syntheticPipelineSteps).toEqual([
      'Create Project',
      'Generate Inventory',
      'Normalize',
      'Run Data Quality',
      'Generate Preview',
    ]);
  });

  it('detects completed synthetic pipeline', () => {
    expect(isSyntheticPipelineComplete([...syntheticPipelineSteps])).toBe(true);
    expect(isSyntheticPipelineComplete(['Create Project'])).toBe(false);
  });
});
