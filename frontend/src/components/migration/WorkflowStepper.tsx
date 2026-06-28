import React from 'react';

interface WorkflowStepperProps {
  steps: string[];
  currentStep: string;
}

const stepLabels: Record<string, string> = {
  draft: 'Draft',
  preparing: 'Preparing',
  validating: 'Validating',
  previewing: 'Previewing',
  migrating: 'Migrating',
  completed: 'Completed',
  failed: 'Failed',
  rolled_back: 'Rolled Back',
};

export default function WorkflowStepper({ steps, currentStep }: WorkflowStepperProps) {
  const currentIndex = steps.indexOf(currentStep);

  return (
    <div className="flex items-center justify-between">
      {steps.map((step, index) => {
        const isCompleted = index < currentIndex;
        const isCurrent = index === currentIndex;
        const isUpcoming = index > currentIndex;

        return (
          <React.Fragment key={step}>
            <div className="flex flex-col items-center">
              <div
                className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-colors ${
                  isCompleted
                    ? 'bg-green-500 text-white'
                    : isCurrent
                    ? 'bg-indigo-600 text-white ring-4 ring-indigo-100'
                    : 'bg-gray-200 text-gray-500'
                }`}
              >
                {isCompleted ? (
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                ) : (
                  index + 1
                )}
              </div>
              <span
                className={`mt-2 text-xs font-medium ${
                  isCurrent ? 'text-indigo-600' : isCompleted ? 'text-green-600' : 'text-gray-500'
                }`}
              >
                {stepLabels[step] || step}
              </span>
            </div>
            {index < steps.length - 1 && (
              <div
                className={`flex-1 h-1 mx-2 rounded ${
                  isCompleted ? 'bg-green-500' : 'bg-gray-200'
                }`}
              />
            )}
          </React.Fragment>
        );
      })}
    </div>
  );
}
