import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMigrationProject, useMigrationReport } from '../../hooks/useMigration';

export default function MigrationReport() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project } = useMigrationProject(projectId);
  const { data: report, isLoading } = useMigrationReport(projectId);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">Loading report...</div>
      </div>
    );
  }

  const reportData = report?.data;

  if (!reportData) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">No report available yet</div>
      </div>
    );
  }

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '-';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hours > 0) return `${hours}h ${minutes}m ${secs}s`;
    if (minutes > 0) return `${minutes}m ${secs}s`;
    return `${secs}s`;
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-5xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            ← Back to Project
          </button>
          <h1 className="text-2xl font-bold text-gray-900">Migration Report</h1>
          <p className="mt-1 text-gray-600">{project?.data?.name}</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white rounded-lg shadow p-4">
            <div className="text-sm text-gray-500">Total Records</div>
            <div className="text-2xl font-bold text-gray-900">
              {reportData.totals?.total_records || 0}
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4">
            <div className="text-sm text-gray-500">Successful</div>
            <div className="text-2xl font-bold text-green-600">
              {reportData.totals?.success || 0}
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4">
            <div className="text-sm text-gray-500">Skipped</div>
            <div className="text-2xl font-bold text-yellow-600">
              {reportData.totals?.skipped || 0}
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4">
            <div className="text-sm text-gray-500">Failed</div>
            <div className="text-2xl font-bold text-red-600">
              {reportData.totals?.failed || 0}
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Summary</h2>
            <dl className="space-y-3">
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Duration</dt>
                <dd className="text-sm font-medium text-gray-900">
                  {formatDuration(reportData.duration_seconds)}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Success Rate</dt>
                <dd className="text-sm font-medium text-gray-900">
                  {reportData.totals?.success_rate || 0}%
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Core Version</dt>
                <dd className="text-sm font-medium text-gray-900">
                  {reportData.core_version || '-'}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Migration Version</dt>
                <dd className="text-sm font-medium text-gray-900">
                  {reportData.migration_version || '-'}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Legacy Version</dt>
                <dd className="text-sm font-medium text-gray-900">
                  {reportData.legacy_version || '-'}
                </dd>
              </div>
            </dl>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Verification</h2>
            <dl className="space-y-3">
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Package Hash</dt>
                <dd className="text-sm font-mono text-gray-900 break-all">
                  {reportData.package_hash || '-'}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Backup Path</dt>
                <dd className="text-sm text-gray-900 break-all">
                  {reportData.backup_path || '-'}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Status</dt>
                <dd className="text-sm font-medium text-gray-900 capitalize">
                  {reportData.status}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-sm text-gray-500">Generated At</dt>
                <dd className="text-sm text-gray-900">
                  {new Date(reportData.created_at).toLocaleString()}
                </dd>
              </div>
            </dl>
          </div>
        </div>

        {reportData.summary?.tables && (
          <div className="bg-white rounded-lg shadow mb-6">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Table Breakdown</h2>
            </div>
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Imported</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Skipped</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {Object.entries(reportData.summary.tables).map(([table, stats]: [string, any]) => (
                  <tr key={table}>
                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{table}</td>
                    <td className="px-6 py-4 text-sm text-gray-900">{stats.total}</td>
                    <td className="px-6 py-4 text-sm text-green-600">{stats.imported}</td>
                    <td className="px-6 py-4 text-sm text-yellow-600">{stats.skipped}</td>
                    <td className="px-6 py-4 text-sm text-red-600">{stats.failed}</td>
                    <td className="px-6 py-4 text-sm text-gray-900">{stats.success_rate}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {reportData.summary?.top_errors && reportData.summary.top_errors.length > 0 && (
          <div className="bg-white rounded-lg shadow">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Top Errors</h2>
            </div>
            <div className="p-6">
              <table className="min-w-full">
                <thead>
                  <tr>
                    <th className="text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                    <th className="text-right text-xs font-medium text-gray-500 uppercase">Count</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {reportData.summary.top_errors.map(([error, count]: [string, number], idx: number) => (
                    <tr key={idx}>
                      <td className="py-2 text-sm text-gray-900">{error}</td>
                      <td className="py-2 text-sm text-right text-gray-900">{count}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
