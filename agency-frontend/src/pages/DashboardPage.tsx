import React from 'react'
import LpgDashboard from './admin/LpgDashboard'

export const DashboardPage: React.FC = () => {
  return (
    <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <LpgDashboard />
    </div>
  )
}