import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  lang: 'en-US',
  title: 'Tragwerk',
  description:
    'Self-hosted PHP application hosting on your own VPS — Docker, FrankenPHP and Traefik, driven by a single XML config.',
  cleanUrls: true,
  appearance: true,

  markdown: {
    // Pair each site theme with a matching Shiki theme: light tokens on a light
    // block in light mode, light tokens on a dark block in dark mode. Pinning a
    // single dark theme made light mode dark-on-light and unreadable.
    theme: { light: 'github-light', dark: 'github-dark' },
  },

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    ['link', { rel: 'icon', type: 'image/png', sizes: '32x32', href: '/favicon-32.png' }],
    ['link', { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' }],
    ['meta', { name: 'theme-color', content: '#3b82f6' }],
    ['meta', { name: 'og:type', content: 'website' }],
    ['meta', { name: 'og:title', content: 'Tragwerk Documentation' }],
    ['meta', { name: 'og:image', content: '/icon-512.png' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/introduction', activeMatch: '/guide/' },
      {
        text: 'Install',
        link: '/install/requirements',
        activeMatch: '/install/',
      },
      { text: 'App', link: '/app/projects', activeMatch: '/app/' },
      { text: 'Config', link: '/config/overview', activeMatch: '/config/' },
      {
        text: 'Server',
        link: '/server/requirements',
        activeMatch: '/server/',
      },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Guide',
          items: [
            { text: 'Introduction', link: '/guide/introduction' },
            { text: 'Core Concepts', link: '/guide/concepts' },
            { text: 'Getting Started', link: '/guide/getting-started' },
          ],
        },
      ],
      '/install/': [
        {
          text: 'Self-Hosting Tragwerk',
          items: [
            { text: 'Requirements', link: '/install/requirements' },
            { text: 'Docker Compose', link: '/install/docker-compose' },
            { text: 'Upgrades', link: '/install/upgrades' },
            { text: 'Backup & Restore', link: '/install/backup' },
          ],
        },
      ],
      '/app/': [
        {
          text: 'Account & Teams',
          items: [
            { text: 'Account', link: '/app/account' },
            { text: 'Two-Factor Auth', link: '/app/two-factor' },
            { text: 'Teams & Roles', link: '/app/teams' },
          ],
        },
        {
          text: 'Projects & Deploys',
          items: [
            { text: 'Projects', link: '/app/projects' },
            { text: 'Environments', link: '/app/environments' },
            { text: 'Deployments', link: '/app/deployments' },
            { text: 'Domains & SSL', link: '/app/domains' },
            { text: 'Environment Variables', link: '/app/variables' },
            { text: 'Integrations & Webhooks', link: '/app/integrations' },
          ],
        },
        {
          text: 'Runtime & Operations',
          items: [
            { text: 'Services', link: '/app/services' },
            { text: 'Cron Jobs', link: '/app/cronjobs' },
            { text: 'Workers', link: '/app/workers' },
            { text: 'Metrics', link: '/app/metrics' },
            { text: 'Logs & Containers', link: '/app/logs-containers' },
            {
              text: 'Registries & Credentials',
              link: '/app/registries-credentials',
            },
          ],
        },
      ],
      '/config/': [
        {
          text: 'XML Configuration',
          items: [
            { text: 'Overview', link: '/config/overview' },
            { text: 'Applications', link: '/config/applications' },
            { text: 'Web & Locations', link: '/config/web' },
            { text: 'PHP Settings', link: '/config/php' },
            { text: 'Workers', link: '/config/workers' },
            { text: 'Cron Jobs', link: '/config/crons' },
            { text: 'Hooks', link: '/config/hooks' },
            { text: 'Mounts', link: '/config/mounts' },
            { text: 'Relationships', link: '/config/relationships' },
            { text: 'Services', link: '/config/services' },
            { text: 'Routes', link: '/config/routes' },
            { text: 'Example Configs', link: '/config/examples' },
          ],
        },
      ],
      '/server/': [
        {
          text: 'Server',
          items: [
            { text: 'Requirements', link: '/server/requirements' },
            { text: 'Servers', link: '/server/servers' },
            { text: 'Server Setup', link: '/server/server-setup' },
            {
              text: 'Architecture on the Host',
              link: '/server/architecture-on-host',
            },
          ],
        },
      ],
    },

    search: {
      provider: 'local',
    },

    outline: { level: [2, 3] },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/benjaminhirsch/tragwerk' },
    ],

    footer: {
      message: 'Tragwerk — self-hosted PHP application hosting.',
      copyright: 'Documentation built with VitePress.',
    },
  },
})
