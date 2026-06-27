import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCreateMigrationProject } from '../../hooks/useMigration';

const sourceTypes = [
  { value: 'firebird', label: 'Firebird Database' },
  { value: 'mysql', label: 'MySQL Database' },
  { value: 'sql_server', label: 'SQL Server' },
  { value: 'postgres', label: 'PostgreSQL' },
  { value: 'oracle', label: 'Oracle' },
  { value: 'zip', label: 'ZIP Package (CSV files)' },
];

export default function ProjectCreate() {
  const navigate = useNavigate();
  const createMutation = useCreateMigrationProject();

  const [form, setForm] = useState({
    name: '',
    description: '',
    source_type: 'firebird',
    source_config: {} as Record<string, any>,
    data_cutoff_date: '',
  });

  const [sourceConfig, setSourceConfig] = useState({
    host: 'localhost',
    port: '3050',
    database: '',
    username: 'SYSDBA',
    password: '',
    path: '',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    const config = form.source_type === 'zip'
      ? { path: sourceConfig.path }
      : {
          host: sourceConfig.host,
          port: sourceConfig.port,
          database: sourceConfig.database,
          username: sourceConfig.username,
          password: sourceConfig.password,
        };

    const result = await createMutation.mutateAsync({
      ...form,
      source_config: config,
      data_cutoff_date: form.data_cutoff_date || null,
    });

    navigate(`/migration/projects/${result.data.id}`);
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-2xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">New Migration Project</h1>
          <p className="mt-1 text-gray-600">Create a new data migration project</p>
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
              placeholder="e.g., Firebird Legacy Migration"
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
              onChange={(e) => setForm({ ...form, source_type: e.target.value })}
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
            <p className="mt-1 text-sm text-gray-500">
              Only migrate data up to this date (optional)
            </p>
          </div>

          <div className="border-t pt-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">Source Configuration</h3>

            {form.source_type === 'zip' ? (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  ZIP File Path *
                </label>
                <input
                  type="text"
                  required
                  value={sourceConfig.path}
                  onChange={(e) => setSourceConfig({ ...sourceConfig, path: e.target.value })}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="/path/to/legacy_data.zip"
                />
              </div>
            ) : (
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Host *
                  </label>
                  <input
                    type="text"
                    required
                    value={sourceConfig.host}
                    onChange={(e) => setSourceConfig({ ...sourceConfig, host: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Port *
                  </label>
                  <input
                    type="text"
                    required
                    value={sourceConfig.port}
                    onChange={(e) => setSourceConfig({ ...sourceConfig, port: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Database *
                  </label>
                  <input
                    type="text"
                    required
                    value={sourceConfig.database}
                    onChange={(e) => setSourceConfig({ ...sourceConfig, database: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="/path/to/database.fdb"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Username
                  </label>
                  <input
                    type="text"
                    value={sourceConfig.username}
                    onChange={(e) => setSourceConfig({ ...sourceConfig, username: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Password
                  </label>
                  <input
                    type="password"
                    value={sourceConfig.password}
                    onChange={(e) => setSourceConfig({ ...sourceConfig, password: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
              </div>
            )}
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
          </div>
        </form>
      </div>
    </div>
  );
}
