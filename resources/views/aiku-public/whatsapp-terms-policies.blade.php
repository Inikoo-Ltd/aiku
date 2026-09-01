<x-aiku-public.layout
    title="WhatsApp Business integration — Terms & Privacy Policy — aiku"
    description="Terms of service and privacy policy covering the aiku WhatsApp Business integration: what data we process, why, how long we keep it and how to have it deleted.">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'WhatsApp Business integration — Terms & Privacy Policy',
            'description' => 'Terms of service and privacy policy for the aiku WhatsApp Business integration.',
            'dateModified' => $effectiveDate->toDateString(),
            'publisher' => ['@type' => 'Organization', 'name' => 'aiku'],
            'mainEntityOfPage' => url()->current(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    </x-slot:head>
    <div class="narrow">
        <article class="post">
            <div class="eyebrow">Legal</div>
            <h1 style="font-size:clamp(34px,4.6vw,52px)">WhatsApp Business integration — Terms &amp; Privacy Policy</h1>
            <div class="meta">
                <time datetime="{{ $effectiveDate->toDateString() }}">Effective {{ $effectiveDate->format('j F Y') }}</time>
                · aiku, by Inikoo Ltd
            </div>

            <div class="body">
                <aside class="tldr">
                    <strong>In short</strong>
                    aiku connects your own WhatsApp Business Account to your aiku organisation so you can talk to your
                    customers from inside aiku. We process those conversations on your behalf, we never sell them, we
                    never use them for advertising, and you can disconnect and have them deleted at any time.
                </aside>

                <aside class="wayfinder">
                    <strong>On this page</strong>
                    <ul>
                        <li><a href="#terms">Terms of service</a> — what the integration is and the rules for using it</li>
                        <li><a href="#privacy">Privacy policy</a> — what we collect, why, and who sees it</li>
                        <li><a href="#deletion">Data deletion</a> — how to remove your data and revoke access</li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </aside>

                <h2 id="who-we-are">Who we are</h2>
                <p>
                    aiku is an open source operating system for commerce, published by Inikoo Ltd ("aiku", "we", "us").
                    This document covers the WhatsApp Business integration only. It sits alongside, and does not
                    replace, the agreement under which you use the rest of aiku.
                </p>
                <p>
                    Throughout this page, "you" means the business that connects a WhatsApp Business Account to aiku,
                    and "your customers" means the people that business exchanges WhatsApp messages with.
                </p>

                <h2 id="terms">Terms of service</h2>

                <h3>1. Acceptance</h3>
                <p>
                    By connecting a WhatsApp Business Account to aiku, or by sending or receiving WhatsApp messages
                    through aiku, you accept these terms. If you do not accept them, do not connect the integration.
                </p>

                <h3>2. What the integration does</h3>
                <p>
                    The integration uses the WhatsApp Business Platform (Cloud API) provided by Meta Platforms, Inc. to:
                </p>
                <ul>
                    <li>link your WhatsApp Business Account and its phone numbers to your aiku organisation;</li>
                    <li>send and receive messages, including template messages, media and interactive messages;</li>
                    <li>show those conversations in aiku next to the customer, order or delivery they belong to;</li>
                    <li>send transactional notifications you configure, such as order confirmations and dispatch updates;</li>
                    <li>record delivery, read and failure statuses so you can see what happened to a message.</li>
                </ul>
                <p>
                    aiku is an intermediary. WhatsApp itself is operated by Meta, and your use of it is also governed by
                    the <a href="https://www.whatsapp.com/legal/business-terms" rel="noopener">WhatsApp Business Terms of Service</a>,
                    the <a href="https://business.whatsapp.com/policy" rel="noopener">WhatsApp Business Messaging Policy</a>
                    and Meta's commerce and platform policies. Where those policies conflict with anything here, they win.
                </p>

                <h3>3. Eligibility and account requirements</h3>
                <ul>
                    <li>You must own, or be authorised to administer, the WhatsApp Business Account you connect.</li>
                    <li>The account must be in good standing with Meta and pass any verification Meta requires.</li>
                    <li>You must use a phone number you control and that is not already registered to personal WhatsApp.</li>
                    <li>You are responsible for every action taken through aiku by your own users.</li>
                </ul>

                <h3>4. Your responsibilities</h3>
                <p>You agree that, when messaging through aiku, you will:</p>
                <ul>
                    <li>obtain and record valid opt-in from each recipient before messaging them, as WhatsApp requires;</li>
                    <li>honour opt-outs promptly and stop messaging anyone who asks you to;</li>
                    <li>only send content that matches the template category you registered it under;</li>
                    <li>not send spam, bulk unsolicited marketing, deceptive content, or anything prohibited by the
                        WhatsApp Business Messaging Policy or Meta's Commerce Policy;</li>
                    <li>not use the integration for illegal goods, adult content, gambling, weapons, or any other
                        restricted category;</li>
                    <li>not attempt to scrape, resell or redistribute WhatsApp platform data;</li>
                    <li>give your customers your own privacy notice explaining that you use WhatsApp to contact them.</li>
                </ul>
                <p>
                    You are the sender of every message leaving your account. Quality ratings, template rejections,
                    number restrictions and account bans applied by Meta are Meta's decisions, and we cannot reverse them.
                </p>

                <h3>5. Fees</h3>
                <p>
                    Meta charges for WhatsApp conversations directly to your WhatsApp Business Account, according to
                    Meta's own pricing. Those charges are yours. Any aiku subscription fee for the integration is set out
                    separately in your aiku agreement.
                </p>

                <h3>6. Availability, suspension and termination</h3>
                <p>
                    We aim to keep the integration available but do not guarantee uninterrupted service; it depends on
                    the availability of Meta's platform, which is outside our control. We may suspend or disable the
                    integration for your organisation if we reasonably believe it is being used in breach of these terms
                    or of Meta's policies, if Meta requires it, or if it threatens the security of the platform.
                </p>
                <p>
                    You may disconnect the integration at any time from your aiku settings. Disconnection revokes our
                    access token and stops all message traffic immediately.
                </p>

                <h3>7. Intellectual property</h3>
                <p>
                    aiku is open source software licensed under AGPL-3.0. Your business data and your message content
                    remain yours. WhatsApp, Meta and their logos belong to Meta Platforms, Inc.; we use them only to
                    identify the service and claim no association beyond being a platform integrator.
                </p>

                <h3>8. Disclaimers and liability</h3>
                <p>
                    The integration is provided "as is". To the fullest extent permitted by law we exclude implied
                    warranties, and we are not liable for indirect or consequential loss, lost profits, lost goodwill,
                    or for messages that are delayed, undelivered or blocked by Meta. Nothing here limits liability that
                    cannot lawfully be limited.
                </p>

                <h3>9. Changes</h3>
                <p>
                    We may update these terms. Material changes will be announced on this page with a new effective date,
                    and where the change affects how personal data is handled we will give notice before it takes effect.
                    Continuing to use the integration after that date means you accept the update.
                </p>

                <h3>10. Governing law</h3>
                <p>
                    These terms are governed by the laws of England and Wales, and the courts of England and Wales have
                    exclusive jurisdiction, without prejudice to any mandatory consumer or data protection rights you
                    have where you live.
                </p>

                <h2 id="privacy">Privacy policy</h2>

                <h3>11. Our role</h3>
                <p>
                    For the conversations you hold with your customers, you are the data controller and aiku is your
                    data processor: we process message data on your documented instructions and for no purpose of our
                    own. For your own account and billing data, and for our service logs, aiku is the controller.
                </p>

                <h3>12. What we collect</h3>
                <table>
                    <thead>
                    <tr><th>Category</th><th>Examples</th></tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Connection data</td>
                        <td>WhatsApp Business Account ID, phone number ID, display name, verification status, access
                            tokens issued by Meta, webhook subscriptions</td>
                    </tr>
                    <tr>
                        <td>Message data</td>
                        <td>Message text, attached media, timestamps, message and conversation IDs, delivery, read and
                            error statuses, template names and parameters</td>
                    </tr>
                    <tr>
                        <td>Contact data</td>
                        <td>Your customer's WhatsApp phone number and WhatsApp profile name, plus the aiku customer
                            record it is matched to</td>
                    </tr>
                    <tr>
                        <td>Your user data</td>
                        <td>Which of your aiku users sent, read or assigned a conversation</td>
                    </tr>
                    <tr>
                        <td>Technical data</td>
                        <td>API request and webhook logs, IP addresses, error traces, rate limit counters</td>
                    </tr>
                    </tbody>
                </table>
                <p>
                    We do not ask for, and the integration is not designed to carry, special category data such as
                    health, biometric or political data. If your customers volunteer it in a message, it is stored as
                    ordinary message content and you remain responsible for handling it lawfully.
                </p>

                <h3>13. Why we process it</h3>
                <ul>
                    <li><b>To deliver the service</b> — routing messages, matching them to customers and orders, and
                        showing conversation history. Lawful basis: performance of a contract.</li>
                    <li><b>To keep the platform working</b> — debugging, fraud and abuse prevention, capacity planning,
                        and enforcing rate limits. Lawful basis: legitimate interests.</li>
                    <li><b>To meet legal obligations</b> — accounting records and responding to lawful requests.</li>
                </ul>
                <p>
                    We do <b>not</b> use WhatsApp message content or contact data for advertising, for profiling, for
                    building marketing lists of our own, or to train machine learning models. We do not sell it, and we
                    do not share it with data brokers.
                </p>

                <h3>14. Who we share it with</h3>
                <p>
                    Only where necessary to run the service, and only under contracts that oblige them to protect it:
                </p>
                <ul>
                    <li><b>Meta Platforms, Inc.</b> — as the operator of the WhatsApp Business Platform, to send and
                        receive your messages;</li>
                    <li><b>Our hosting and infrastructure providers</b> — for the servers, databases and object storage
                        that hold your aiku instance;</li>
                    <li><b>Error monitoring and logging providers</b> — which may incidentally receive technical data;</li>
                    <li><b>Authorities</b> — where we are legally required to disclose, and where lawful we will tell you first.</li>
                </ul>
                <p>
                    If aiku is ever involved in a merger or acquisition, data may transfer to the successor entity under
                    the same commitments, and you will be told.
                </p>

                <h3>15. Where it is stored, and for how long</h3>
                <p>
                    Data is stored on servers in the European Union and the United Kingdom. Where a provider processes
                    data outside those regions, the transfer relies on an adequacy decision or on Standard Contractual
                    Clauses.
                </p>
                <ul>
                    <li><b>Message content and conversation history</b> — kept while your account is active, then deleted
                        within 90 days of the account closing or of your deletion request.</li>
                    <li><b>Access tokens</b> — deleted immediately when you disconnect the integration or revoke access
                        in Meta Business Manager.</li>
                    <li><b>Media files</b> — kept with the conversation they belong to, and deleted with it.</li>
                    <li><b>Technical and webhook logs</b> — retained up to 30 days, then rotated out.</li>
                    <li><b>Billing and accounting records</b> — retained as long as tax law requires, typically 6 years.</li>
                </ul>
                <p>
                    Backups are retained on a rolling schedule and expire within 35 days, so deleted data can persist in
                    backups for that period before it disappears.
                </p>

                <h3>16. Security</h3>
                <p>
                    Traffic is encrypted in transit with TLS. Access tokens are stored encrypted at rest. Access to
                    production data is limited to the staff who need it, is authenticated with multi-factor
                    authentication, and is logged. We patch dependencies regularly and review our own code before it
                    ships. No system is perfectly secure, and where a breach affects your data we will notify you without
                    undue delay so that you can meet your own 72-hour notification duty.
                </p>

                <h3>17. Your rights and your customers' rights</h3>
                <p>
                    Depending on where they live, individuals may ask to access, correct, delete, export or restrict the
                    processing of their personal data, or object to it. Because you are the controller of your
                    conversations, requests from your customers should go to you; you can act on most of them yourself
                    inside aiku, and we will help with anything you cannot. Requests about your own aiku account can come
                    straight to us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
                </p>
                <p>
                    You also have the right to complain to a supervisory authority, such as the UK Information
                    Commissioner's Office or your local EU data protection authority.
                </p>

                <h2 id="deletion">18. Data deletion</h2>
                <p>You can remove your WhatsApp data from aiku in any of these ways:</p>
                <ul>
                    <li><b>Disconnect in aiku</b> — open your organisation's channel settings, choose the connected
                        WhatsApp Business Account and select <i>Disconnect</i>. This revokes the token straight away and
                        queues the stored conversations for deletion.</li>
                    <li><b>Revoke in Meta</b> — in Meta Business Manager, open <i>Business settings → Apps</i> and remove
                        aiku's access. Message traffic stops immediately; email us to have stored data erased too.</li>
                    <li><b>Ask us</b> — email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> from an address
                        registered on the account with the subject "WhatsApp data deletion". We confirm within 7 days and
                        complete the deletion within 30 days, with backups aging out inside 35 days after that.</li>
                </ul>
                <p>
                    Deletion is irreversible. We keep only what law requires us to keep, such as invoices, and anonymised
                    counters that cannot identify anyone.
                </p>

                <h3>19. Children</h3>
                <p>
                    The integration is a business tool and is not directed at children. We do not knowingly process the
                    data of anyone under 16 in this context; tell us if you believe we have, and we will delete it.
                </p>

                <h3>20. Cookies</h3>
                <p>
                    This page sets no marketing cookies. The wider aiku application uses only the cookies needed to keep
                    you signed in and to keep the session secure.
                </p>

                <h3>21. Changes to this policy</h3>
                <p>
                    Changes are published here with an updated effective date. Where a change materially affects how
                    personal data is handled, we will give notice before it takes effect.
                </p>

                <h2 id="contact">22. Contact</h2>
                <p>
                    Inikoo Ltd, trading as aiku.<br>
                    Privacy and integration questions: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a><br>
                    Source code and issue tracker:
                    <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener">github.com/Inikoo-Ltd/aiku</a>
                </p>

                <aside class="tldr bottom">
                    <strong>Related</strong>
                    Meta's own requirements for integrations like this one are set out in the
                    <a href="https://developers.facebook.com/documentation/development/terms-and-policies/privacy-policy" rel="noopener">Meta Platform privacy policy guidance</a>
                    and the <a href="https://business.whatsapp.com/policy" rel="noopener">WhatsApp Business Messaging Policy</a>.
                </aside>
            </div>
        </article>
    </div>
</x-aiku-public.layout>
