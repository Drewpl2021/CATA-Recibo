import { Injectable } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class ThemeService {
  private readonly THEME_KEY = 'app-theme';
  private readonly DARK_CLASS = 'dark-mode';

  constructor() {
    this.initTheme();
  }

  toggleTheme(): void {
    if (document.documentElement.classList.contains(this.DARK_CLASS)) {
      this.enableLightMode();
    } else {
      this.enableDarkMode();
    }
  }

  enableDarkMode(): void {
    document.documentElement.classList.add(this.DARK_CLASS);
    localStorage.setItem(this.THEME_KEY, 'dark');
  }

  enableLightMode(): void {
    document.documentElement.classList.remove(this.DARK_CLASS);
    localStorage.setItem(this.THEME_KEY, 'light');
  }

  isDarkMode(): boolean {
    return document.documentElement.classList.contains(this.DARK_CLASS);
  }

  private initTheme(): void {
    const savedTheme = localStorage.getItem(this.THEME_KEY);
    if (savedTheme === 'dark') {
      this.enableDarkMode();
    } else {
      this.enableLightMode();
    }
  }
}
