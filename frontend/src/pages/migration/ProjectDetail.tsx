import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  useMigrationProject,
  usePrepareMigration,
  useValidateMigration,
  useRollbackMigration,
  useBundlePreview,
  useExportBundle,
} from '../../hooks/useMigration';
import WorkflowStepper from '../../components/migration/WorkflowStepper';
import migrationApi from '../../services/migrationApi';

const statusColors: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-800',
  preparing: 'bg-blue-100 text-blue-800',
  validating: 'bg-yellow-100 text-yellow-800',
  previewing: 'bg-indigo-100 text-indigo-800',
  migrating: 'bg-purple-100 text-purple-800',
  completed: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-800',
  rolled_back: 'bg-orange-100 text-orange-800',
};

const statusSteps = ['draft', 'preparing', 'validating', 'previewing', 'migrating', 'completed'];

export default function ProjectDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project, isLoading } = useMigrationProject(projectId);
  const { data: bundlePreview } = useBundlePreview(projectId);
  const prepareMutation = usePrepareMigration(projectId);
  const validateMutation = useValidateMigration(projectId);
  const rollbackMutation = useRollbackMigration(projectId);
  const exportBundleMutation = useExportBundle(projectId);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">Loading project...</div>
      </div>
    );
  }

  if (!project?.data) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">Project not found</div>
      </div>
    );
  }

  const data = project.data;
  const currentStepIndex = statusSteps.indexOf(data.status);

  const handleAction = async (action: string) => {
    switch (action) {
      case 'prepare':
        await prepareMutation.mutateAsync();
        break;
      case 'validate':
        await validateMutation.mutateAsync();
        break;
      case 'export_bundle':
        if (confirm('Export a Migration Bundle? This will not modify SCHF Core.')) {
          const result = await exportBundleMutation.mutateAsync();
          if (result.data.success) {
            window.location.href = migrationApi.bundle.downloadUrl(projectId);
          }
        }
        break;
      case 'rollback':
        if (confirm('Are you sure you want to rollback? This will revert all imported data.')) {
          await rollbackMutation.mutateAsync();
        }
        break;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate('/migration/projects')}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            ← Back to Projects
          </button>
          <div className="flex items-center gap-4">
            <h1 className="text-2xl font-bold text-gray-900">{data.name}</h1>
            <span className={`px-3 py-1 text-sm font-medium rounded-full ${statusColors[data.status]}`}>
              {data.status}
            </span>
          </div>
          {data.description && (
            <p className="mt-2 text-gray-600">{data.description}</p>
          )}
        </div>

        <div className="mb-8">
          <WorkflowStepper
            steps={statusSteps}
            currentStep={data.status}
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-sm font-medium text-gray-500 mb-2">Source Type</h3>
            <p className="text-lg font-semibold text-gray-900 capitalize">
              {data.source_type.replace('_', ' ')}
            </p>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-sm font-medium text-gray-500 mb-2">Progress</h3>
            <div className="flex items-center gap-3">
              <div className="flex-1 bg-gray-200 rounded-full h-3">
                <div
                  className="bg-indigo-600 h-3 rounded-full transition-all"
                  style={{ width: `${data.progress || 0}%` }}
                />
              </div>
              <span className="text-lg font-semibold text-gray-900">{data.progress || 0}%</span>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-sm font-medium text-gray-500 mb-2">Created</h3>
            <p className="text-lg font-semibold text-gray-900">
              {new Date(data.created_at).toLocaleDateString()}
            </p>
          </div>
        </div>

        {data.error_message && (
          <div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <h3 className="text-sm font-medium text-red-800 mb-1">Error</h3>
            <p className="text-sm text-red-700">{data.error_message}</p>
          </div>
        )}

        <div className="bg-white rounded-lg shadow p-6 mb-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
          <div className="flex flex-wrap gap-3">
            {data.status === 'draft' && (
              <button
                onClick={() => handleAction('prepare')}
                disabled={prepareMutation.isPending}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
              >
                {prepareMutation.isPending ? 'Preparing...' : 'Start Preparation'}
              </button>
            )}
            {data.status === 'validating' && (
              <button
                onClick={() => handleAction('validate')}
                disabled={validateMutation.isPending}
                className="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors disabled:opacity-50"
              >
                {validateMutation.isPending ? 'Validating...' : 'Validate Data'}
              </button>
            )}
            {data.status === 'previewing' && (
              <button
                onClick={() => navigate(`/migration/projects/${projectId}/preview`)}
                className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
              >
                Preview Data
              </button>
            )}
            {data.status === 'previewing' && (
              <button
                onClick={() => handleAction('export_bundle')}
                disabled={exportBundleMutation.isPending}
                className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors disabled:opacity-50"
              >
                {exportBundleMutation.isPending ? 'Exporting...' : 'Export Bundle'}
              </button>
            )}
            {['completed', 'failed'].includes(data.status) && (
              <button
                onClick={() => handleAction('rollback')}
                disabled={rollbackMutation.isPending}
                className="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
              >
                {rollbackMutation.isPending ? 'Rolling back...' : 'Rollback'}
              </button>
            )}
            <button
              onClick={() => navigate(`/migration/projects/${projectId}/ai-config`)}
              className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
            >
              AI Settings
            </button>
            {data.status === 'completed' && (
              <button
                onClick={() => navigate(`/migration/projects/${projectId}/report`)}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
              >
                View Report
              </button>
            )}
          </div>
        </div>

        {bundlePreview?.data && (
          <div className="bg-white rounded-lg shadow p-6 mb-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Migration Bundle Preview</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
              <div>
                <p className="text-sm text-gray-500">Bundle Version</p>
                <p className="font-semibold text-gray-900">{bundlePreview.data.bundle_version}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Source Tables</p>
                <p className="font-semibold text-gray-900">{bundlePreview.data.source.tables}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Bundle Files</p>
                <p className="font-semibold text-gray-900">{bundlePreview.data.files.length}</p>
              </div>
            </div>
            {bundlePreview.data.warnings.length > 0 && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p className="text-sm font-medium text-yellow-800 mb-2">Warnings</p>
                <ul className="text-sm text-yellow-700 space-y-1">
                  {bundlePreview.data.warnings.map((warning) => (
                    <li key={warning}>{warning}</li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}

        {data.imports && data.imports.length > 0 && (
          <div className="bg-white rounded-lg shadow">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Import Batches</h3>
            </div>
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Batch</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Imported</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {data.imports.map((imp) => (
                  <tr key={imp.id}>
                    <td className="px-6 py-4 text-sm text-gray-900">#{imp.batch_number}</td>
                    <td className="px-6 py-4 text-sm text-gray-900">{imp.table_name}</td>
                    <td className="px-6 py-4 text-sm text-gray-900">{imp.records_total}</td>
                    <td className="px-6 py-4 text-sm text-green-600">{imp.records_imported}</td>
                    <td className="px-6 py-4 text-sm text-red-600">{imp.records_failed}</td>
                    <td className="px-6 py-4">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${statusColors[imp.status] || 'bg-gray-100 text-gray-800'}`}>
                        {imp.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
