import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMigrationProject, useUpdateMigrationProject, useTestConnection } from '../../hooks/useMigration';

export default function SourceConfig() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project, isLoading } = useMigrationProject(projectId);
  const updateMutation = useUpdateMigrationProject();
  const testConnectionMutation = useTestConnection(projectId);

  const [config, setConfig] = useState<Record<string, any>>({});

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">Loading...</div>
      </div>
    );
  }

  const data = project?.data;
  if (!data) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">Project not found</div>
      </div>
    );
  }

  const currentConfig = { ...data.source_config, ...config };

  const handleSave = async () => {
    await updateMutation.mutateAsync({
      id: projectId,
      data: { source_config: currentConfig },
    });
    navigate(`/migration/projects/${projectId}`);
  };

  const handleTestConnection = async () => {
    if (data.source_type === 'synthetic') {
      alert('Synthetic source does not require a connection test.');
      return;
    }

    const result = await testConnectionMutation.mutateAsync();
    if (result.data.success) {
      alert('Connection successful!');
    } else {
      alert('Connection failed: ' + result.data.message);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-2xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            ← Back to Project
          </button>
          <h1 className="text-2xl font-bold text-gray-900">Source Configuration</h1>
          <p className="mt-1 text-gray-600">
            Configure the {data.source_type.replace('_', ' ')} data source
          </p>
        </div>

        <div className="bg-white rounded-lg shadow p-6 space-y-6">
          {data.source_type === 'synthetic' ? (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Synthetic Scenario
              </label>
              <select
                value={currentConfig.scenario || 'clean'}
                onChange={(e) => setConfig({ ...config, scenario: e.target.value })}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="clean">Clean</option>
                <option value="warnings">Warnings</option>
                <option value="blocked">Blocked</option>
              </select>
              <p className="mt-1 text-sm text-gray-500">
                This local mode generates controlled fake records and never connects to Firebird or real data.
              </p>
            </div>
          ) : data.source_type === 'zip' ? (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                ZIP File Path *
              </label>
              <input
                type="text"
                value={currentConfig.path || ''}
                onChange={(e) => setConfig({ ...config, path: e.target.value })}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="/path/to/legacy_data.zip"
              />
              <p className="mt-1 text-sm text-gray-500">
                Full path to the ZIP file containing CSV exports
              </p>
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Host *
                  </label>
                  <input
                    type="text"
                    value={currentConfig.host || 'localhost'}
                    onChange={(e) => setConfig({ ...config, host: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Port *
                  </label>
                  <input
                    type="text"
                    value={currentConfig.port || ''}
                    onChange={(e) => setConfig({ ...config, port: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Database Path / Name *
                </label>
                <input
                  type="text"
                  value={currentConfig.database || ''}
                  onChange={(e) => setConfig({ ...config, database: e.target.value })}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="/path/to/database.fdb"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Username
                  </label>
                  <input
                    type="text"
                    value={currentConfig.username || ''}
                    onChange={(e) => setConfig({ ...config, username: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Password
                  </label>
                  <input
                    type="password"
                    value={currentConfig.password || ''}
                    onChange={(e) => setConfig({ ...config, password: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  />
                </div>
              </div>
            </>
          )}

          <div className="flex justify-between pt-4">
            <button
              onClick={handleTestConnection}
              disabled={data.source_type === 'synthetic' || testConnectionMutation.isPending}
              className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-50"
            >
              {data.source_type === 'synthetic' ? 'No Connection Needed' : testConnectionMutation.isPending ? 'Testing...' : 'Test Connection'}
            </button>
            <div className="flex gap-3">
              <button
                onClick={() => navigate(`/migration/projects/${projectId}`)}
                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleSave}
                disabled={updateMutation.isPending}
                className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
              >
                {updateMutation.isPending ? 'Saving...' : 'Save Configuration'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
