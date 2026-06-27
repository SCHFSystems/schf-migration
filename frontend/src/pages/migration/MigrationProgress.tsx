import React, { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMigrationProject } from '../../hooks/useMigration';
import ImportProgress from '../../components/migration/ImportProgress';

export default function MigrationProgress() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = parseInt(id || '0', 10);

  const { data: project, refetch } = useMigrationProject(projectId);

  useEffect(() => {
    if (project?.data?.status === 'migrating') {
      const interval = setInterval(() => {
        refetch();
      }, 3000);

      return () => clearInterval(interval);
    }
  }, [project?.data?.status, refetch]);

  const data = project?.data;

  if (!data) {
    return (
      <div className="min-h-screen bg-gray-50 p-6 flex items-center justify-center">
        <div className="text-gray-500">Loading...</div>
      </div>
    );
  }

  const totalRecords = data.imports?.reduce((sum, imp) => sum + imp.records_total, 0) || 0;
  const importedRecords = data.imports?.reduce((sum, imp) => sum + imp.records_imported, 0) || 0;
  const failedRecords = data.imports?.reduce((sum, imp) => sum + imp.records_failed, 0) || 0;

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-4xl mx-auto">
        <div className="mb-6">
          <button
            onClick={() => navigate(`/migration/projects/${projectId}`)}
            className="text-sm text-gray-500 hover:text-gray-700 mb-2"
          >
            ← Back to Project
          </button>
          <h1 className="text-2xl font-bold text-gray-900">Migration Progress</h1>
          <p className="mt-1 text-gray-600">{data.name}</p>
        </div>

        <div className="bg-white rounded-lg shadow p-6 mb-6">
          <div className="mb-6">
            <div className="flex justify-between items-center mb-2">
              <span className="text-sm font-medium text-gray-700">Overall Progress</span>
              <span className="text-sm font-medium text-gray-900">
                {totalRecords > 0 ? Math.round((importedRecords / totalRecords) * 100) : 0}%
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-4">
              <div
                className="bg-indigo-600 h-4 rounded-full transition-all duration-500"
                style={{
                  width: `${totalRecords > 0 ? (importedRecords / totalRecords) * 100 : 0}%`,
                }}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4 text-center">
            <div>
              <div className="text-2xl font-bold text-gray-900">{totalRecords}</div>
              <div className="text-sm text-gray-500">Total Records</div>
            </div>
            <div>
              <div className="text-2xl font-bold text-green-600">{importedRecords}</div>
              <div className="text-sm text-gray-500">Imported</div>
            </div>
            <div>
              <div className="text-2xl font-bold text-red-600">{failedRecords}</div>
              <div className="text-sm text-gray-500">Failed</div>
            </div>
          </div>
        </div>

        {data.status === 'migrating' && (
          <div className="bg-white rounded-lg shadow p-6 mb-6">
            <div className="flex items-center gap-3">
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-indigo-600" />
              <span className="text-gray-700">Migration in progress...</span>
            </div>
          </div>
        )}

        {data.imports && data.imports.length > 0 && (
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-900">Import Batches</h2>
            {data.imports.map((imp) => (
              <ImportProgress key={imp.id} importData={imp} />
            ))}
          </div>
        )}

        {data.status === 'completed' && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <div className="text-green-600 text-lg font-semibold mb-2">
              Migration Completed Successfully!
            </div>
            <button
              onClick={() => navigate(`/migration/projects/${projectId}/report`)}
              className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
              View Report
            </button>
          </div>
        )}

        {data.status === 'failed' && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-6">
            <div className="text-red-600 text-lg font-semibold mb-2">
              Migration Failed
            </div>
            {data.error_message && (
              <p className="text-sm text-red-700 mb-4">{data.error_message}</p>
            )}
            <button
              onClick={() => navigate(`/migration/projects/${projectId}`)}
              className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              Back to Project
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
