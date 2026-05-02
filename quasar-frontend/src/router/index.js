import { route } from 'quasar/wrappers'
import { createRouter, createMemoryHistory, createWebHistory, createWebHashHistory } from 'vue-router'
import routes from './routes'

export default route(function (/* { store, ssrContext } */) {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : (process.env.VUE_ROUTER_MODE === 'history' ? createWebHistory : createWebHashHistory)

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,

    history: createHistory(process.env.VUE_ROUTER_BASE)
  })

  Router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token')
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
    const isSuperAdmin = to.path.startsWith('/super-admin')
    
    // Verificar autenticación para rutas protegidas
    if (requiresAuth && !token) {
      next({ name: 'login', query: { redirect: to.fullPath } })
      return
    }
    
    // Verificar SuperAdmin para rutas de superadmin
    if (isSuperAdmin) {
      const user = JSON.parse(localStorage.getItem('user') || '{}')
      if (!user.is_super_admin) {
        next({ name: 'dashboard' })
        return
      }
    }
    
    next()
  })

  return Router
})
