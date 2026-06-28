import React from 'react';

interface DataTableProps {
  data: Record<string, any>[];
  maxRows?: number;
}

export default function DataTable({ data, maxRows = 10 }: DataTableProps) {
  if (!data || data.length === 0) {
    return (
      <div className="p-4 text-center text-gray-500">
        No data available
      </div>
    );
  }

  const columns = Object.keys(data[0]);
  const displayData = data.slice(0, maxRows);

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((col) => (
              <th
                key={col}
                className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
              >
                {col}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {displayData.map((row, idx) => (
            <tr key={idx} className="hover:bg-gray-50">
              {columns.map((col) => (
                <td
                  key={col}
                  className="px-4 py-3 text-sm text-gray-900 max-w-xs truncate"
                  title={String(row[col] ?? '')}
                >
                  {formatValue(row[col])}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      {data.length > maxRows && (
        <div className="px-4 py-3 text-sm text-gray-500 text-center border-t">
          Showing {maxRows} of {data.length} rows
        </div>
      )}
    </div>
  );
}

function formatValue(value: any): string {
  if (value === null || value === undefined) {
    return '-';
  }

  if (typeof value === 'object') {
    return JSON.stringify(value);
  }

  return String(value);
}
