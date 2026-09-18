<template>
  <div class="profile-menu">
    <button
      type="button"
      class="pwa-action"
      :aria-expanded="panelOpen"
      aria-controls="vue-webhook-panel"
      @click="togglePanel"
    >
      Webhooks
    </button>
    <section v-if="panelOpen" id="vue-webhook-panel" class="profile-panel" aria-labelledby="vue-webhook-title">
      <h2 id="vue-webhook-title">Outgoing webhooks</h2>
      <form @submit.prevent="createWebhook">
        <label for="vue-webhook-url">Endpoint URL</label>
        <input
          id="vue-webhook-url"
          v-model="draft.url"
          type="url"
          maxlength="2048"
          required
          placeholder="https://example.com/hooks/chat"
        >

        <fieldset>
          <legend>Events</legend>
          <label v-for="event in supportedEvents" :key="event">
            <input v-model="draft.events" type="checkbox" :value="event"> {{ event }}
          </label>
        </fieldset>
        <button type="submit" class="send-btn" :disabled="saving || draft.events.length === 0">
          {{ saving ? 'Creating…' : 'Create webhook' }}
        </button>
      </form>

      <div v-if="createdSecret" class="webhook-secret" role="status">
        <strong>Copy this signing secret now. It will not be shown again.</strong>
        <code>{{ createdSecret }}</code>
      </div>

      <p v-if="loading" role="status">Loading webhooks…</p>
      <article v-for="webhook in webhooks" :key="webhook.id" class="webhook-card">
        <h3>{{ webhook.url }}</h3>
        <p>{{ webhook.events.join(', ') }}</p>
        <label>
          <input v-model="webhook.active" type="checkbox" @change="saveWebhook(webhook)">
          Active
        </label>
        <div class="form-actions">
          <button type="button" class="clear-btn" @click="loadDeliveries(webhook)">Delivery history</button>
          <button type="button" class="clear-btn" @click="deleteWebhook(webhook)">Delete</button>
        </div>
        <ul v-if="selectedWebhookId === webhook.id" class="webhook-deliveries">
          <li v-for="delivery in deliveries" :key="delivery.id">
            <strong>{{ delivery.event }}</strong>
            — {{ delivery.status }} ({{ delivery.attempts }} attempt{{ delivery.attempts === 1 ? '' : 's' }})
            <button
              v-if="!['pending', 'retrying', 'processing'].includes(delivery.status)"
              type="button"
              class="clear-btn"
              @click="redeliver(delivery)"
            >
              Redeliver
            </button>
            <span v-if="delivery.last_error"> — {{ delivery.last_error }}</span>
          </li>
          <li v-if="deliveries.length === 0">No deliveries yet.</li>
        </ul>
      </article>

      <p v-if="status" class="profile-status" role="status">{{ status }}</p>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </section>
  </div>
</template>

<script>
  import { csrfToken, readJson } from '../js/profile.js';

  export default {
    data() {
      return {
        panelOpen: false,
        loading: false,
        saving: false,
        status: '',
        error: '',
        createdSecret: '',
        webhooks: [],
        deliveries: [],
        selectedWebhookId: null,
        supportedEvents: ['message.created', 'reaction.added', 'channel.created'],
        draft: { url: '', events: ['message.created'], active: true },
      };
    },
    methods: {
      togglePanel() {
        this.panelOpen = !this.panelOpen;
        if (this.panelOpen) void this.loadWebhooks();
      },
      async request(url, options = {}) {
        const headers = { ...(options.headers || {}) };
        if (options.method && options.method !== 'GET') headers['X-CSRF-TOKEN'] = csrfToken();
        const response = await fetch(url, { ...options, headers });
        this.refreshCsrfToken(response);
        return readJson(response);
      },
      refreshCsrfToken(response) {
        const token = response.headers.get('X-CSRF-TOKEN');
        if (token) document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
      },
      async loadWebhooks() {
        this.loading = true;
        this.error = '';
        try {
          const payload = await this.request(this.$chatRoutes.webhooks);
          this.webhooks = payload.webhooks;
        } catch (error) {
          this.error = error.message;
        } finally {
          this.loading = false;
        }
      },
      async createWebhook() {
        this.saving = true;
        this.error = '';
        this.status = '';
        this.createdSecret = '';
        try {
          const payload = await this.request(this.$chatRoutes.webhooks, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.draft),
          });
          this.createdSecret = payload.secret;
          this.draft = { url: '', events: ['message.created'], active: true };
          this.status = 'Webhook created.';
          await this.loadWebhooks();
        } catch (error) {
          this.error = error.message;
        } finally {
          this.saving = false;
        }
      },
      async saveWebhook(webhook) {
        this.error = '';
        try {
          await this.request(`${this.$chatRoutes.webhooks}/${webhook.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: webhook.active }),
          });
          this.status = 'Webhook updated.';
        } catch (error) {
          this.error = error.message;
          await this.loadWebhooks();
        }
      },
      async deleteWebhook(webhook) {
        if (!window.confirm(`Delete webhook ${webhook.url}?`)) return;
        this.error = '';
        try {
          const response = await fetch(`${this.$chatRoutes.webhooks}/${webhook.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
          });
          this.refreshCsrfToken(response);
          if (!response.ok) {
            const payload = await response.json();
            throw new Error(payload?.error?.message || 'The webhook could not be deleted.');
          }
          this.status = 'Webhook deleted.';
          this.selectedWebhookId = null;
          await this.loadWebhooks();
        } catch (error) {
          this.error = error.message;
        }
      },
      async loadDeliveries(webhook) {
        this.error = '';
        try {
          const payload = await this.request(`${this.$chatRoutes.webhooks}/${webhook.id}/deliveries`);
          this.selectedWebhookId = webhook.id;
          this.deliveries = payload.deliveries;
        } catch (error) {
          this.error = error.message;
        }
      },
      async redeliver(delivery) {
        this.error = '';
        try {
          await this.request(`${this.$chatRoutes.webhookDeliveries}/${delivery.id}/redeliver`, { method: 'POST' });
          delivery.status = 'pending';
          delivery.attempts = 0;
          this.status = 'Delivery queued again.';
        } catch (error) {
          this.error = error.message;
        }
      },
    },
  };
</script>
