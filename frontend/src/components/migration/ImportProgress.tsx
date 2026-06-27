import React from 'react';
import { MigrationImport } from '../../services/migrationApi';

interface ImportProgressProps {
  importData: MigrationImport;
}

const statusColors: Record<string, string> = {
  pending: 'bg-gray-100 text-gray-800',
  running: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-800',
  cancelled: 'bg-yellow-100 text-yellow-800',
};

export default function ImportProgress({ importData }: ImportProgressProps) {
  const progress =
    importData.records_total > 0
      ? Math.round((importData.records_imported / importData.records_total) * 100)
      : 0;

  return (
    <div className="bg-white rounded-lg shadow p-4">
      <div className="flex items-center justify-between mb-3">
        <div>
          <h3 className="font-medium text-gray-900">
            Batch #{importData.batch_number}: {importData.table_name}
          </h3>
          <p className="text-sm text-gray-500">
            {importData.records_total} total records
          </p>
        </div>
        <span
          className={`px-3 py-1 text-xs font-medium rounded-full ${
            statusColors[importData.status] || 'bg-gray-100 text-gray-800'
          }`}
        >
          {importData.status}
        </span>
      </div>

      <div className="mb-3">
        <div className="flex justify-between text-sm text-gray-600 mb-1">
          <span>Progress</span>
          <span>{progress}%</span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2">
          <div
            className={`h-2 rounded-full transition-all ${
              importData.status === 'failed' ? 'bg-red-500' : 'bg-indigo-600'
            }`}
            style={{ width: `${progress}%` }}
          />
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4 text-center text-sm">
        <div>
          <div className="font-medium text-green-600">{importData.records_imported}</div>
          <div className="text-gray-500">Imported</div>
        </div>
        <div>
          <div className="font-medium text-yellow-600">{importData.records_skipped}</div>
          <div className="text-gray-500">Skipped</div>
        </div>
        <div>
          <div className="font-medium text-red-600">{importData.records_failed}</div>
          <div className="text-gray-500">Failed</div>
        </div>
      </div>

      {importData.error_log && importData.error_log.length > 0 && (
        <div className="mt-3 p-3 bg-red-50 rounded-lg">
          <div className="text-xs font-medium text-red-600 mb-1">Errors:</div>
          <ul className="text-xs text-red-700 space-y-1">
            {importData.error_log.slice(0, 5).map((error: any, idx: number) => (
              <li key={idx}>• {error.message || JSON.stringify(error)}</li>
            ))}
          </ul>
        </div>
      )}

      {importData.success_rate > 0 && (
        <div className="mt-3 text-right text-sm text-gray-600">
          Success rate: {importData.success_rate}%
        </div>
      )}
    </div>
  );
}
