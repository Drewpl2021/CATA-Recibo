import { HttpInterceptorFn } from '@angular/common/http';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = localStorage.getItem('auth_token');
  const headers: Record<string, string> = {
    Accept: 'application/json'
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const method = req.method.toUpperCase();
  // Evitar error en PHP 8.2 local (XAMPP) convirtiendo PUT, PATCH, DELETE a POST con el parámetro ?_method
  if (['PUT', 'PATCH', 'DELETE'].includes(method)) {
    const separator = req.url.includes('?') ? '&' : '?';
    const newUrl = `${req.url}${separator}_method=${method}`;
    const overrideReq = req.clone({
      method: 'POST',
      url: newUrl,
      setHeaders: headers
    });
    return next(overrideReq);
  }

  return next(req.clone({ setHeaders: headers }));
};
