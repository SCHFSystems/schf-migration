export const syntheticPipelineSteps = [
  'Create Project',
  'Generate Inventory',
  'Normalize',
  'Run Data Quality',
  'Generate Preview',
] as const;

export function isSyntheticPipelineComplete(completedSteps: string[]): boolean {
  return syntheticPipelineSteps.every((step) => completedSteps.includes(step));
}
