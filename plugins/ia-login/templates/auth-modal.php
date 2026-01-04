<?php if (!defined('ABSPATH')) exit; ?>
<!-- IA Login: Atrium Auth Modal (Login / Register / Forgot) -->
<div id="ia-atrium-auth" class="ia-modal" aria-hidden="true">
  <div class="ia-modal-backdrop" data-ia-auth-close></div>

  <div class="ia-modal-card" role="dialog" aria-modal="true" aria-label="Log in or register">
    <div class="ia-modal-header">
      <div class="ia-modal-title">Welcome</div>
      <button type="button" class="ia-icon-btn" data-ia-auth-close aria-label="Close">×</button>
    </div>

    <div class="ia-modal-body">
      <div class="ia-auth-tabs" role="tablist" aria-label="Authentication">
        <button type="button" class="ia-auth-tab active" data-auth-tab="login" role="tab" aria-selected="true">Log in</button>
        <button type="button" class="ia-auth-tab" data-auth-tab="register" role="tab" aria-selected="false">Register</button>
        <button type="button" class="ia-auth-tab" data-auth-tab="forgot" role="tab" aria-selected="false">Forgot</button>
      </div>

      <div class="ia-auth-panels">
        <!-- Login -->
        <section class="ia-auth-panel active" data-auth-panel="login" role="tabpanel" aria-label="Login">
          <form class="ia-auth-form" data-action="ia_auth_login">
            <input type="hidden" name="nonce" value="" />
            <input type="hidden" name="redirect_to" value="" data-ia-redirect-to />

            <label class="ia-field">
              <span class="ia-label">Username or Email</span>
              <input class="ia-input" type="text" name="identifier" autocomplete="username" required>
            </label>

            <label class="ia-field">
              <span class="ia-label">Password</span>
              <input class="ia-input" type="password" name="password" autocomplete="current-password" required>
            </label>

            <label class="ia-check">
              <input type="checkbox" name="remember" value="1">
              <span>Remember me</span>
            </label>

            <button class="ia-btn ia-btn-primary" type="submit">Log in</button>
            <div class="ia-auth-msg" aria-live="polite"></div>

            <div class="ia-auth-links">
              <a href="#" data-ia-auth-goto="forgot">Forgot password?</a>
            </div>
          </form>
        </section>

        <!-- Register -->
        <section class="ia-auth-panel" data-auth-panel="register" role="tabpanel" aria-label="Register">
          <form class="ia-auth-form" data-action="ia_auth_register">
            <input type="hidden" name="nonce" value="" />
            <input type="hidden" name="redirect_to" value="" data-ia-redirect-to />

            <label class="ia-field">
              <span class="ia-label">Username</span>
              <input class="ia-input" type="text" name="username" autocomplete="username" required>
            </label>

            <label class="ia-field">
              <span class="ia-label">Email</span>
              <input class="ia-input" type="email" name="email" autocomplete="email" required>
            </label>

            <label class="ia-field">
              <span class="ia-label">Password</span>
              <input class="ia-input" type="password" name="password" autocomplete="new-password" required>
            </label>

            <label class="ia-field">
              <span class="ia-label">Confirm password</span>
              <input class="ia-input" type="password" name="password2" autocomplete="new-password" required>
            </label>

            <button class="ia-btn ia-btn-primary" type="submit">Register</button>
            <div class="ia-auth-msg" aria-live="polite"></div>
          </form>
        </section>

        <!-- Forgot password -->
        <section class="ia-auth-panel" data-auth-panel="forgot" role="tabpanel" aria-label="Forgot password">
          <form class="ia-auth-form" data-action="ia_auth_forgot">
            <input type="hidden" name="nonce" value="" />
            <input type="hidden" name="redirect_to" value="" data-ia-redirect-to />

            <label class="ia-field">
              <span class="ia-label">Email or Username</span>
              <input class="ia-input" type="text" name="identifier" autocomplete="username" required>
            </label>

            <button class="ia-btn ia-btn-primary" type="submit">Send reset email</button>
            <div class="ia-auth-msg" aria-live="polite"></div>

            <div class="ia-auth-hint" style="margin-top:10px; opacity:.9; font-size:13px;">
              You’ll receive an email with a secure link to choose a new password.
              If you do not receive the email or have issues logging in, contact <strong>admin@indieagora.com</strong>.
            </div>
          </form>
        </section>

        <!-- Post-register notice -->
        <section class="ia-auth-panel" data-auth-panel="notice" role="tabpanel" aria-label="Verification notice" style="display:none;">
          <div class="ia-auth-card" style="background:transparent; border:none; padding:0;">
            <h2 style="margin:0 0 10px; font-size:18px;">Check your email</h2>
            <p style="margin:0 0 10px; opacity:.95;">
              Please check for the verification email in your inbox in order to log in.
              If you do not receive the email or are having issues logging in, contact <strong>admin@indieagora.com</strong>.
            </p>
            <button type="button" class="ia-btn ia-btn-primary" data-ia-auth-goto="login">Continue</button>
          </div>
        </section>
      </div>
    </div>

    <div class="ia-modal-footer">
      <button type="button" class="ia-btn" data-ia-auth-close>Close</button>
    </div>
  </div>
</div>
