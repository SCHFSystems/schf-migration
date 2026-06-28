import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMigrationProject, useGeneratePreview, usePreviewResult } from '../../hooks/useMigration';

export default function PreviewPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project } = useMigrationProject(projectId);
  const { data: previewResult, isLoading: isLoadingPreview } = usePreviewResult(projectId);
  const generateMutation = useGeneratePreview(projectId);

  const preview = previewResult?.data;

  const handleGenerate = async () => {
    await generateMutation.mutateAsync();
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            &larr; Back to Project
          </button>
          <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold text-gray-900">Migration Preview</h1>
            <button
              onClick={handleGenerate}
              disabled={generateMutation.isPending}
              className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
            >
              {generateMutation.isPending ? 'Generating...' : 'Generate Preview'}
            </button>
          </div>
          <p className="mt-1 text-gray-600">
            Review what will be migrated before generating the Bundle
          </p>
        </div>

        {isLoadingPreview ? (
          <div className="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            Loading preview...
          </div>
        ) : preview ? (
          <div className="space-y-6">
            <div className="flex items-center gap-3">
              <span
                className={`px-4 py-2 text-lg font-bold rounded-full ${
                  preview.ready_for_bundle
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
                }`}
              >
                {preview.ready_for_bundle ? 'Ready for Bundle' : 'Blocked'}
              </span>
              <span className="text-gray-500 text-sm">
                Generated at: {new Date(preview.generated_at).toLocaleString()}
              </span>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
              <div className="bg-white rounded-lg shadow p-4">
                <p className="text-sm text-gray-500">Total Records</p>
                <p className="text-2xl font-bold text-gray-900">{preview.summary.total_records}</p>
              </div>
              <div className="bg-white rounded-lg shadow p-4">
                <p className="text-sm text-gray-500">Valid</p>
                <p className="text-2xl font-bold text-green-600">{preview.summary.valid_records}</p>
              </div>
              <div className="bg-white rounded-lg shadow p-4">
                <p className="text-sm text-gray-500">Warnings</p>
                <p className="text-2xl font-bold text-yellow-600">{preview.summary.warning_count}</p>
              </div>
              <div className="bg-white rounded-lg shadow p-4">
                <p className="text-sm text-gray-500">Errors</p>
                <p className="text-2xl font-bold text-red-600">{preview.summary.error_count}</p>
              </div>
              <div className="bg-white rounded-lg shadow p-4">
                <p className="text-sm text-gray-500">Ignored</p>
                <p className="text-2xl font-bold text-gray-500">{preview.summary.ignored_count}</p>
              </div>
              <div className="bg-white rounded-lg shadow p-4">
                <p className="text-sm text-gray-500">Historical</p>
                <p className="text-2xl font-bold text-blue-600">{preview.summary.historical_count}</p>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Entities</h2>
              </div>
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valid</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warnings</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ignored</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Historical</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {Object.entries(preview.entities).map(([name, e]: [string, any]) => (
                    <tr key={name}>
                      <td className="px-6 py-4 text-sm font-medium capitalize">{name}</td>
                      <td className="px-6 py-4 text-sm text-gray-900">{e.total}</td>
                      <td className="px-6 py-4 text-sm text-green-600">{e.valid}</td>
                      <td className="px-6 py-4 text-sm text-yellow-600">{e.warnings}</td>
                      <td className="px-6 py-4 text-sm text-red-600">{e.errors}</td>
                      <td className="px-6 py-4 text-sm text-gray-500">{e.ignored}</td>
                      <td className="px-6 py-4 text-sm text-blue-600">{e.historical}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {preview.errors.length > 0 && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 className="text-sm font-medium text-red-800 mb-2">Errors ({preview.errors.length})</h3>
                <ul className="space-y-1">
                  {preview.errors.map((err, i) => (
                    <li key={i} className="text-sm text-red-700">
                      <span className="font-medium">[{err.entity}]</span> {err.message} (field: {err.field}, id: {err.external_id})
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {preview.warnings.length > 0 && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 className="text-sm font-medium text-yellow-800 mb-2">Warnings ({preview.warnings.length})</h3>
                <ul className="space-y-1">
                  {preview.warnings.map((w, i) => (
                    <li key={i} className="text-sm text-yellow-700">
                      <span className="font-medium">[{w.entity}]</span> {w.message} (field: {w.field}, id: {w.external_id})
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {preview.ignored.length > 0 && (
              <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 className="text-sm font-medium text-gray-800 mb-2">Ignored ({preview.ignored.length})</h3>
                <ul className="space-y-1">
                  {preview.ignored.map((ig, i) => (
                    <li key={i} className="text-sm text-gray-600">
                      <span className="font-medium">[{ig.entity}]</span> {ig.reason} (id: {ig.external_id})
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {preview.historical.length > 0 && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 className="text-sm font-medium text-blue-800 mb-2">Historical ({preview.historical.length})</h3>
                <ul className="space-y-1">
                  {preview.historical.map((h, i) => (
                    <li key={i} className="text-sm text-blue-700">
                      <span className="font-medium">[{h.entity}]</span> {h.reason} (id: {h.external_id})
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {!preview.ready_for_bundle && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 className="text-sm font-medium text-red-800">Bundle generation is blocked</h3>
                <p className="text-sm text-red-700 mt-1">
                  Fix all errors before generating the migration bundle. Warnings do not block bundle generation.
                </p>
              </div>
            )}
          </div>
        ) : (
          <div className="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            No preview generated yet. Click "Generate Preview" to analyze your migration data.
          </div>
        )}

        <div className="mt-6 flex justify-end">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Back to Project
          </button>
        </div>
      </div>
    </div>
  );
}
