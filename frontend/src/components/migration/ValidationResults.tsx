import React from 'react';
import { ValidationResult } from '../../services/migrationApi';

interface ValidationResultsProps {
  results: ValidationResult;
}

export default function ValidationResults({ results }: ValidationResultsProps) {
  const sections = [
    {
      title: 'Schema Validation',
      data: results.schema_validation,
      icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
    },
    {
      title: 'Referential Integrity',
      data: results.referential_integrity,
      icon: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101',
    },
    {
      title: 'Duplicate Detection',
      data: results.duplicate_detection,
      icon: 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
    },
    {
      title: 'Business Rules',
      data: results.business_rules,
      icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
    {
      title: 'Data Quality',
      data: results.data_quality,
      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 mb-6">
        <div className={`px-4 py-2 rounded-lg ${results.overall_valid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
          <span className="font-medium">
            {results.overall_valid ? 'Validation Passed' : 'Validation Failed'}
          </span>
        </div>
        <div className="text-sm text-gray-600">
          {results.error_count} errors, {results.warning_count} warnings
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {sections.map((section) => (
          <div
            key={section.title}
            className="bg-white rounded-lg shadow p-4 border-l-4"
            style={{
              borderLeftColor: section.data?.valid ? '#10b981' : '#ef4444',
            }}
          >
            <div className="flex items-center gap-3 mb-3">
              <svg
                className={`w-5 h-5 ${section.data?.valid ? 'text-green-500' : 'text-red-500'}`}
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d={section.icon}
                />
              </svg>
              <h3 className="font-medium text-gray-900">{section.title}</h3>
            </div>

            <div className="text-sm text-gray-600 mb-2">
              {section.data?.error_count || 0} errors, {section.data?.warning_count || 0} warnings
            </div>

            {section.data?.issues && section.data.issues.length > 0 && (
              <div className="mt-2">
                <div className="text-xs font-medium text-red-600 mb-1">Issues:</div>
                <ul className="text-xs text-red-700 space-y-1">
                  {section.data.issues.slice(0, 3).map((issue: any, idx: number) => (
                    <li key={idx}>• {issue.message || JSON.stringify(issue)}</li>
                  ))}
                </ul>
              </div>
            )}

            {section.data?.warnings && section.data.warnings.length > 0 && (
              <div className="mt-2">
                <div className="text-xs font-medium text-yellow-600 mb-1">Warnings:</div>
                <ul className="text-xs text-yellow-700 space-y-1">
                  {section.data.warnings.slice(0, 3).map((warning: any, idx: number) => (
                    <li key={idx}>• {warning.message || JSON.stringify(warning)}</li>
                  ))}
                </ul>
              </div>
            )}

            {section.data?.quality_score !== undefined && (
              <div className="mt-2">
                <div className="text-xs font-medium text-gray-600 mb-1">Quality Score:</div>
                <div className="flex items-center gap-2">
                  <div className="flex-1 bg-gray-200 rounded-full h-2">
                    <div
                      className={`h-2 rounded-full ${
                        section.data.quality_score >= 80
                          ? 'bg-green-500'
                          : section.data.quality_score >= 50
                          ? 'bg-yellow-500'
                          : 'bg-red-500'
                      }`}
                      style={{ width: `${section.data.quality_score}%` }}
                    />
                  </div>
                  <span className="text-xs text-gray-600">{section.data.quality_score}%</span>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
