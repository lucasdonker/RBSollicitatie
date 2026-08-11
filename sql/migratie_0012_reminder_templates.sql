-- Migratie 0012: Reminder mail templates
-- Voegt reminder-templates toe zodat ze bewerkbaar zijn via Mail templates pagina

INSERT IGNORE INTO `mail_templates` (`slug`, `naam`, `beschrijving`, `onderwerp`, `body_html`) VALUES
('reminder_blk',
 'Reminder: BLK invullen',
 'Wordt automatisch verstuurd aan sollicitanten die hun BLK formulier nog niet hebben ingevuld',
 'Vergeet niet je BLK formulier in te vullen — Paal 13',
 '<p>Hoi {{voornaam}},</p>
<p>We zien dat je je hebt aangemeld bij Paal 13, maar het uitgebreide formulier (BLK) nog niet hebt ingevuld. Vul dit alsjeblieft in zodat we je sollicitatie verder kunnen beoordelen.</p>
<p style="text-align:center;margin:24px 0"><a href="{{portaal_url}}" style="background:#FF6B35;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:600">Naar het portaal</a></p>
<p>Vragen? Mail terug.</p>
<p>Groet,<br>Reddingsbrigade Paal 13</p>'),

('reminder_sd_aankomst',
 'Reminder: selectiedag bijna zover',
 'Wordt X dagen vóór de selectiedag verstuurd aan alle uitgenodigden',
 'Bijna zover: selectiedag — Paal 13',
 '<p>Hoi {{voornaam}},</p>
<p>Het is bijna zover! De selectiedag is op <strong>{{selectiedag_datum}}</strong>{{selectiedag_tijd}} bij {{selectiedag_locatie}}.</p>
<p>Bekijk in het portaal alle praktische info voor de dag.</p>
<p style="text-align:center;margin:24px 0"><a href="{{portaal_url}}" style="background:#FF6B35;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:600">Naar portaal</a></p>
<p>Tot dan!<br>Reddingsbrigade Paal 13</p>'),

('reminder_sd_morgen',
 'Reminder: selectiedag morgen',
 'Wordt 1 dag vóór de selectiedag verstuurd met laatste praktische info',
 'Morgen al: selectiedag — Paal 13',
 '<p>Hoi {{voornaam}},</p>
<p>Morgen is het zover — de selectiedag bij Paal 13!</p>
<h3>Praktische info</h3>
<ul>
  <li><strong>Wanneer:</strong> {{selectiedag_datum}}{{selectiedag_tijd}}</li>
  <li><strong>Waar:</strong> {{selectiedag_locatie}}</li>
  <li><strong>Wat meenemen:</strong> zwemkleding, handdoek, sportkleding, eventueel zonnebrandcrème</li>
</ul>
<p>{{laatste_info}}</p>
<p>Veel succes en tot morgen!<br>Reddingsbrigade Paal 13</p>');
