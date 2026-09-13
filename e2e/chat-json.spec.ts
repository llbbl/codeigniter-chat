import { chatScenario } from './support/chat-scenarios';

chatScenario({
  name: 'JSON chat',
  path: '/chat/json',
  messageInput: '#content',
  submitButton: 'input[type="submit"]',
  authorInput: '#name',
  network: { path: '/api/v1/messages', format: 'json' },
});
