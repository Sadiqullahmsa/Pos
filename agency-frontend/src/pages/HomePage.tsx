import React from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/Card'
import { Button } from '../components/ui/Button'

export const HomePage: React.FC = () => {
  const features = [
    {
      title: 'Modern Design',
      description: 'Beautiful, responsive UI built with Tailwind CSS and Radix UI components.',
      icon: '🎨'
    },
    {
      title: 'Type Safety',
      description: 'Full TypeScript support with strict type checking and IntelliSense.',
      icon: '🔒'
    },
    {
      title: 'State Management',
      description: 'Efficient state management with Zustand and React Query.',
      icon: '⚡'
    },
    {
      title: 'Authentication',
      description: 'Secure JWT-based authentication with protected routes.',
      icon: '🔐'
    },
    {
      title: 'Dark Mode',
      description: 'Built-in dark mode support with system preference detection.',
      icon: '🌙'
    },
    {
      title: 'Performance',
      description: 'Optimized for performance with lazy loading and code splitting.',
      icon: '🚀'
    }
  ]

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <section className="relative overflow-hidden bg-gradient-to-br from-primary/20 via-background to-secondary/20">
        <div className="absolute inset-0 grid-pattern opacity-20" />
        <div className="relative container mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-32">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8 }}
            className="text-center max-w-4xl mx-auto"
          >
            <h1 className="text-4xl md:text-6xl font-bold text-balance mb-6">
              Modern React App with{' '}
              <span className="gradient-text">All Features</span>
            </h1>
            <p className="text-xl text-muted-foreground mb-8 max-w-2xl mx-auto">
              A complete React application with TypeScript, Tailwind CSS, authentication, 
              state management, and all modern development tools.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button size="lg" asChild className="btn-hover">
                <Link to="/register">Get Started</Link>
              </Button>
              <Button variant="outline" size="lg" asChild>
                <Link to="/login">Sign In</Link>
              </Button>
            </div>
          </motion.div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-20 bg-secondary/20">
        <div className="container mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="text-center mb-16"
          >
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              Everything You Need
            </h2>
            <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
              Built with the latest technologies and best practices for modern web development.
            </p>
          </motion.div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {features.map((feature, index) => (
              <motion.div
                key={feature.title}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: 0.1 * index }}
              >
                <Card className="h-full hover:shadow-lg transition-shadow duration-300">
                  <CardHeader>
                    <div className="text-4xl mb-4">{feature.icon}</div>
                    <CardTitle>{feature.title}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <CardDescription className="text-base">
                      {feature.description}
                    </CardDescription>
                  </CardContent>
                </Card>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20">
        <div className="container mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.4 }}
            className="text-center"
          >
            <Card className="max-w-2xl mx-auto glass">
              <CardHeader>
                <CardTitle className="text-3xl">Ready to Start?</CardTitle>
                <CardDescription className="text-lg">
                  Join thousands of developers using our modern React stack.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Button size="lg" asChild className="btn-hover">
                  <Link to="/register">Create Account</Link>
                </Button>
              </CardContent>
            </Card>
          </motion.div>
        </div>
      </section>
    </div>
  )
}