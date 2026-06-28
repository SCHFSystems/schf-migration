import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMigrationProject, useAiConfigs, useCreateAiConfig } from '../../hooks/useMigration';

const providers = [
  { value: 'openai', label: 'OpenAI' },
  { value: 'nvidia', label: 'NVIDIA NIM' },
  { value: 'glm', label: 'GLM' },
  { value: 'minimax', label: 'MiniMax' },
  { value: 'kimi', label: 'Kimi' },
  { value: 'custom', label: 'Custom' },
];

const defaultModels: Record<string, string> = {
  openai: 'gpt-4o',
  nvidia: 'nvidia/nemotron-4-340b-instruct',
  glm: 'glm-4',
  minimax: 'abab6.5-chat',
  kimi: 'moonshot-v1-128k',
  custom: '',
};

export default function AiConfig() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project } = useMigrationProject(projectId);
  const { data: configs } = useAiConfigs(projectId);
  const createConfigMutation = useCreateAiConfig(projectId);

  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    provider: 'openai',
    api_key: '',
    model: 'gpt-4o',
    temperature: 0.3,
    max_tokens: 4096,
    system_prompt: '',
  });

  const handleProviderChange = (provider: string) => {
    setForm({
      ...form,
      provider,
      model: defaultModels[provider] || '',
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await createConfigMutation.mutateAsync(form);
    setShowForm(false);
    setForm({
      provider: 'openai',
      api_key: '',
      model: 'gpt-4o',
      temperature: 0.3,
      max_tokens: 4096,
      system_prompt: '',
    });
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-3xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            ← Back to Project
          </button>
          <h1 className="text-2xl font-bold text-gray-900">AI Configuration</h1>
          <p className="mt-1 text-gray-600">
            Configure AI provider for data analysis and mapping suggestions
          </p>
        </div>

        {configs?.data && configs.data.length > 0 && (
          <div className="bg-white rounded-lg shadow mb-6">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Active Configuration</h2>
            </div>
            <div className="p-6">
              {configs.data.filter(c => c.is_active).map((config) => (
                <div key={config.id} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">{config.provider_label}</p>
                    <p className="text-sm text-gray-500">Model: {config.model}</p>
                    <p className="text-sm text-gray-500">Temperature: {config.temperature}</p>
                  </div>
                  <span className="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                    Active
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}

        {!showForm ? (
          <button
            onClick={() => setShowForm(true)}
            className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            Add AI Configuration
          </button>
        ) : (
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">New AI Configuration</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Provider *
                </label>
                <select
                  value={form.provider}
                  onChange={(e) => handleProviderChange(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                  {providers.map((p) => (
                    <option key={p.value} value={p.value}>{p.label}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  API Key *
                </label>
                <input
                  type="password"
                  required
                  value={form.api_key}
                  onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Enter your API key"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Model
                </label>
                <input
                  type="text"
                  value={form.model}
                  onChange={(e) => setForm({ ...form, model: e.target.value })}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="e.g., gpt-4o"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Temperature
                  </label>
                  <input
                    type="number"
                    min="0"
                    max="2"
                    step="0.1"
                    value={form.temperature}
                    onChange={(e) => setForm({ ...form, temperature: parseFloat(e.target.value) })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Max Tokens
                  </label>
                  <input
                    type="number"
                    min="1"
                    max="128000"
                    value={form.max_tokens}
                    onChange={(e) => setForm({ ...form, max_tokens: parseInt(e.target.value) })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  System Prompt
                </label>
                <textarea
                  value={form.system_prompt}
                  onChange={(e) => setForm({ ...form, system_prompt: e.target.value })}
                  rows={4}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Optional: Custom system prompt for the AI"
                />
              </div>

              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createConfigMutation.isPending}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                >
                  {createConfigMutation.isPending ? 'Saving...' : 'Save Configuration'}
                </button>
              </div>
            </form>
          </div>
        )}
      </div>
    </div>
  );
}
