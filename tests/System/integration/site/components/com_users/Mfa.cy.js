import { TOTP } from 'totp-generator';

describe('Test in frontend that the user', () => {
  afterEach(() => cy.db_getUserId().then((uid) => cy.task('queryDB', `DELETE FROM #__user_mfa WHERE user_id = ${uid}`)));

  it('can login with Multi-factor Authentication (email)', () => {
    cy.doFrontendLogin();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.task('clearEmails');
    cy.get('.com-users-methods-list-method-name-email a.com-users-methods-list-method-addnew').click();
    cy.get('#com-users-method-edit-title').clear().type('Test Code');
    cy.task('getMails').then((mails) => {
      cy.wrap(mails).should('have.lengthOf', 1);
      cy.wrap(mails[0].headers.subject).should('match', /code is -\d{6}-$/);
      cy.wrap(/code is -(\d{6})-$/.exec(mails[0].headers.subject)[1]).as('code')
        .then((code) => cy.wrap(mails[0].body).should('have.string', `Your authentication code is ${code}.`));
      cy.wrap(mails[0].html).should('be.false');
    });
    cy.get('@code').then((code) => cy.get('#com-users-method-code').clear().type(code));
    cy.get('#com-users-method-edit').submit();
    cy.get('.com-users-methods-list-method-name-email .com-users-methods-list-method-record').contains('Test Code');
    cy.doFrontendLogout();
    cy.get('form.mod-login input[name="username"]').type(Cypress.env('username'));
    cy.get('form.mod-login input[name="password"]').type(Cypress.env('password'));
    cy.get('form.mod-login').submit();
    cy.get('#users-mfa-title').contains('Test Code');
    cy.task('getMails').then((mails) => {
      cy.wrap(mails).should('have.lengthOf', 2);
      cy.wrap(mails[1].headers.subject).should('match', /code is -\d{6}-$/);
      cy.wrap(/code is -(\d{6})-$/.exec(mails[1].headers.subject)[1]).as('code');
    });
    cy.get('@code').then((code) => cy.get('#users-mfa-code').clear().type(code));
    cy.get('#users-mfa-captive-form').submit();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('#com-users-methods-reset-message').contains('is enabled');
    cy.get('.com-users-methods-list-method-name-email a.com-users-methods-list-method-record-delete').click();
    cy.on('window:confirm', (text) => expect(text).to.contains('Are you sure you want to delete?'));
    cy.get('#com-users-methods-reset-message').contains('not enabled');
  });

  it('can login with Multi-factor Authentication (totp)', () => {
    cy.doFrontendLogin();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('.com-users-methods-list-method-name-totp a.com-users-methods-list-method-addnew').click();
    cy.get('#com-users-method-edit-title').clear().type('Test Code');
    cy.get('.com-users-method-edit-tabular-container table tr td')
      .contains('Enter this key')
      .next()
      .invoke('text')
      .then((key) => key.trim())
      .as('secret');
    cy.get('@secret').then((secret) => cy.get('#com-users-method-code').clear().type(TOTP.generate(secret).otp));
    cy.get('#com-users-method-edit').submit();
    cy.get('.com-users-methods-list-method-name-totp .com-users-methods-list-method-record').contains('Test Code');
    cy.doFrontendLogout();
    cy.get('form.mod-login input[name="username"]').type(Cypress.env('username'));
    cy.get('form.mod-login input[name="password"]').type(Cypress.env('password'));
    cy.get('form.mod-login').submit();
    cy.get('#users-mfa-title').contains('Verification code');
    cy.get('@secret').then((secret) => cy.get('#users-mfa-code').clear().type(TOTP.generate(secret).otp));
    cy.get('#users-mfa-captive-form').submit();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('#com-users-methods-reset-message').contains('is enabled');
    cy.get('.com-users-methods-list-method-name-totp a.com-users-methods-list-method-record-delete').click();
    cy.on('window:confirm', (text) => expect(text).to.contains('Are you sure you want to delete?'));
    cy.get('#com-users-methods-reset-message').contains('not enabled');
  });

  it('can login with Multi-factor Authentication (passkey)', { browser: '!firefox' }, () => {
    Cypress.automation('remote:debugger:protocol', { command: 'WebAuthn.enable', params: {} }).then(() => {
      Cypress.automation('remote:debugger:protocol', {
        command: 'WebAuthn.addVirtualAuthenticator',
        params: {
          options: {
            protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true,
          },
        },
      });
    });
    cy.doFrontendLogin();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('.com-users-methods-list-method-name-webauthn a.com-users-methods-list-method-addnew').click();
    cy.get('#com-users-method-edit-title').clear().type('Test Passkey');
    cy.get('#com-users-method-edit button.multifactorauth_webauthn_setup').click();
    cy.get('.com-users-methods-list-method-name-webauthn .com-users-methods-list-method-record').contains('Test Passkey');
    cy.doFrontendLogout();
    cy.get('form.mod-login input[name="username"]').type(Cypress.env('username'));
    cy.get('form.mod-login input[name="password"]').type(Cypress.env('password'));
    cy.get('form.mod-login').submit();
    cy.get('#users-mfa-title').contains('Passkey');
    cy.get('#users-mfa-captive-button-submit').click();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('#com-users-methods-reset-message').contains('is enabled');
    cy.get('.com-users-methods-list-method-name-webauthn a.com-users-methods-list-method-record-delete').click();
    cy.on('window:confirm', (text) => expect(text).to.contains('Are you sure you want to delete?'));
    cy.get('#com-users-methods-reset-message').contains('not enabled');
    cy.then(() => Cypress.automation('remote:debugger:protocol', { command: 'WebAuthn.disable', params: {} }));
  });

  it('can login with Multi-factor Authentication (backup codes)', () => {
    cy.doFrontendLogin();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('.com-users-methods-list-method-name-totp a.com-users-methods-list-method-addnew').click();
    cy.get('#com-users-method-edit-title').clear().type('Test Code');
    cy.get('.com-users-method-edit-tabular-container table tr td')
      .contains('Enter this key')
      .next()
      .invoke('text')
      .then((key) => key.trim())
      .as('secret');
    cy.get('@secret').then((secret) => cy.get('#com-users-method-code').clear().type(TOTP.generate(secret).otp));
    cy.get('#com-users-method-edit').submit();
    cy.get('.com-users-methods-list-method-name-totp .com-users-methods-list-method-record').contains('Test Code');
    cy.get('.com-users-methods-list-method-name-backupcodes .com-users-methods-list-method-record-info a')
      .should('have.text', 'Print these codes')
      .click();
    cy.get('table > tbody > tr > td').first().invoke('text').then((code) => cy.wrap(/\d{8}/.exec(code)[0]).as('code'));
    cy.doFrontendLogout();
    cy.get('form.mod-login input[name="username"]').type(Cypress.env('username'));
    cy.get('form.mod-login input[name="password"]').type(Cypress.env('password'));
    cy.get('form.mod-login').submit();
    cy.get('#users-mfa-title').contains('Verification code');
    cy.get('#users-mfa-captive-form-choose-another a').click();
    cy.get('a.com-users-method').contains('Backup Codes').click();
    cy.get('#users-mfa-title').contains('Backup Codes');
    cy.get('@code').then((code) => cy.get('#users-mfa-code').clear().type(code));
    cy.get('#users-mfa-captive-form').submit();
    cy.visit('/index.php?option=com_users&view=profile&layout=edit');
    cy.get('#com-users-methods-reset-message').contains('is enabled');
    cy.get('.com-users-methods-list-method-name-totp a.com-users-methods-list-method-record-delete').click();
    cy.on('window:confirm', (text) => expect(text).to.contains('Are you sure you want to delete?'));
    cy.get('#com-users-methods-reset-message').contains('not enabled');
  });
});
