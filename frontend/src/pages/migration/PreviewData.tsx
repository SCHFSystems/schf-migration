import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMigrationProject, usePreviewMigration } from '../../hooks/useMigration';
import DataTable from '../../components/migration/DataTable';

export default function PreviewData() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project } = useMigrationProject(projectId);
  const { data: previewData, isLoading } = usePreviewMigration(projectId);
  const [selectedTable, setSelectedTable] = useState<string | null>(null);

  const previews = previewData?.data?.previews || {};
  const tableNames = Object.keys(previews);
  const currentPreview = selectedTable ? previews[selectedTable] : previews[tableNames[0]];

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            ← Back to Project
          </button>
          <h1 className="text-2xl font-bold text-gray-900">Data Preview</h1>
          <p className="mt-1 text-gray-600">
            Preview how your legacy data will be transformed
          </p>
        </div>

        {tableNames.length > 0 && (
          <div className="mb-6 flex gap-2 overflow-x-auto pb-2">
            {tableNames.map((name) => (
              <button
                key={name}
                onClick={() => setSelectedTable(name)}
                className={`px-4 py-2 rounded-lg whitespace-nowrap transition-colors ${
                  (selectedTable || tableNames[0]) === name
                    ? 'bg-indigo-600 text-white'
                    : 'bg-white text-gray-700 hover:bg-gray-100'
                }`}
              >
                {name}
                {previews[name] && (
                  <span className="ml-2 text-sm opacity-75">
                    ({previews[name].total_records} records)
                  </span>
                )}
              </button>
            ))}
          </div>
        )}

        {isLoading ? (
          <div className="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            Loading preview data...
          </div>
        ) : currentPreview ? (
          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Column Mapping</h2>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Source Column
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Target Column
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Type
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Transform
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {Object.entries(currentPreview.column_mapping || {}).map(([source, config]: [string, any]) => (
                      <tr key={source}>
                        <td className="px-4 py-3 text-sm font-mono text-gray-900">{source}</td>
                        <td className="px-4 py-3 text-sm font-mono text-indigo-600">{config.target}</td>
                        <td className="px-4 py-3 text-sm text-gray-600">{config.type}</td>
                        <td className="px-4 py-3 text-sm text-gray-600">{config.transform || 'none'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Original Data (Sample)</h2>
              </div>
              <DataTable data={currentPreview.original} />
            </div>

            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Normalized Data</h2>
              </div>
              <DataTable data={currentPreview.normalized} />
            </div>
          </div>
        ) : (
          <div className="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            No preview data available. Make sure you've connected to your data source.
          </div>
        )}

        <div className="mt-6 flex justify-end">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            Back to Project
          </button>
        </div>
      </div>
    </div>
  );
}
