import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCreateMigrationProject, useCreateSyntheticProject } from '../../hooks/useMigration';

const sourceTypes = [
  { value: 'synthetic', label: 'Synthetic Source' },
];

export default function ProjectCreate() {
  const navigate = useNavigate();
  const createMutation = useCreateMigrationProject();
  const createSyntheticMutation = useCreateSyntheticProject();

  const [form, setForm] = useState({
    name: 'Synthetic Migration Project',
    description: 'Sprint 9 synthetic-only pipeline validation',
    source_type: 'synthetic' as const,
    source_config: {} as Record<string, any>,
    data_cutoff_date: '',
  });

  const [scenario, setScenario] = useState<'clean' | 'warnings' | 'blocked'>('clean');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    const result = await createMutation.mutateAsync({
      ...form,
      source_config: {
        scenario,
        organization: {
          external_id: 'ORG-SYN',
          name: 'Synthetic Organization',
          legal_name: 'Synthetic Organization Ltd',
        },
      },
      data_cutoff_date: form.data_cutoff_date || null,
    });

    navigate(`/migration/projects/${result.data.id}`);
  };

  const handleQuickCreate = async () => {
    const result = await createSyntheticMutation.mutateAsync(scenario);
    navigate(`/migration/projects/${result.data.id}`);
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-2xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Create Synthetic Project</h1>
          <p className="mt-1 text-gray-600">Sprint 9 runs only with controlled fake data.</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow p-6 space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Project Name *
            </label>
            <input
              type="text"
              required
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="Synthetic Migration Project"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Description
            </label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              rows={3}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="Optional description for this migration project"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Source Type *
            </label>
            <select
              value={form.source_type}
              onChange={() => setForm({ ...form, source_type: 'synthetic' })}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
              {sourceTypes.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Data Cutoff Date
            </label>
            <input
              type="date"
              value={form.data_cutoff_date}
              onChange={(e) => setForm({ ...form, data_cutoff_date: e.target.value })}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
            <p className="mt-1 text-sm text-gray-500">Optional metadata for preview classification.</p>
          </div>

          <div className="border-t pt-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">Synthetic Source</h3>
            <label className="block text-sm font-medium text-gray-700 mb-2">Scenario</label>
            <select
              value={scenario}
              onChange={(e) => setScenario(e.target.value as 'clean' | 'warnings' | 'blocked')}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
              <option value="clean">Clean: ready preview</option>
              <option value="warnings">Warnings: non-blocking quality issues</option>
              <option value="blocked">Blocked: errors prevent bundle readiness</option>
            </select>
            <p className="mt-2 text-sm text-gray-500">
              Synthetic data includes suppliers, categories, bank accounts, payables and expenses. No real data is used.
            </p>
          </div>

          <div className="flex justify-end gap-4 pt-4">
            <button
              type="button"
              onClick={() => navigate('/migration/projects')}
              className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
            >
              {createMutation.isPending ? 'Creating...' : 'Create Project'}
            </button>
            <button
              type="button"
              onClick={handleQuickCreate}
              disabled={createSyntheticMutation.isPending}
              className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
            >
              {createSyntheticMutation.isPending ? 'Creating...' : 'Quick Create Synthetic'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
