Here is the verbatim transcription of the audio recording:

**Speaker 1:** ...backup here so we'll have a more structured conversation.

**Speaker 2:** Sure.

**Speaker 1:** Alright, we'll come back, we'll come back to that. Um, alright so as far as WebBee is concerned, what role is that playing in your stack right now?

**Speaker 2:** Uh, the WebBee, what that does is it uh, it goes into the Shopify uh, uh order system and then uh grabs any items that uh, that Amazon will need to fulfill.

**Speaker 1:** Okay, I see. So it's acting as a sort of, sort of a middleware right now?

**Speaker 2:** Yeah, yeah, yeah, it's a middleman. Uh, so if uh Amazon doesn't have any stock or it's not part of their SKU list to ship, then then it would reject the uh, the import to Amazon.

**Speaker 1:** Okay. Um, you guys also mentioned uh TikTok. Um...

**Speaker 2:** Uh yeah, that's in the future. Right now uh uh TikTok is fully fulfilling their orders for uh for one of our other uh um brands. And uh that's in the future in case like you finish the the fulfillment and you finish the order approval processing, the next thing would be to uh improve the uh uh to do the uh sales reports for us for on our on our e-commerce platforms like uh Amazon, TikTok, and Shopify.

**Speaker 1:** Okay, got it. So that, so TikTok is not really in scope.

**Speaker 2:** Not part of the scope. Not part of the scope.

**Speaker 1:** Got it, got it. Um, okay. You guys mentioned an implementation of Sage X3 and it broke your um... let me see what Angeline sent me exactly. Or would you mind just going through that and telling me exactly like how it affected you guys, what broke down?

**Speaker 2:** Oh no, no, no, what uh got us in you know, in needing help was ever since Amazon started doing uh uh doing sort of fulfillments, our e-commerce department uh was making um promotional deals that would uh that would have Amazon ship part of an order and then we would need to fulfill the rest. But the original uh programming that was set to uh to fulfill the Shopify orders was not uh was not set to do partial fulfillments. So I had to wait for the uh the Amazon to fulfill their their part of the order before I could fulfill the rest of the order.

**Speaker 1:** Oh, okay, I see. So it, so really, um, so alright and correct me if I'm wrong, the the problem was that um, the basically the partial the implementation was not able to handle the partial fulfillment problem.

**Speaker 2:** Right, yeah, yeah, that's correct.

**Speaker 1:** Okay. So so what I have to do is I have to look through the orders uh manually to find out what uh what items uh Amazon is handling so I could know that I need to uh ship the rest.

**Speaker 2:** Oh, understood, okay.

**Speaker 1:** Alright, so as far as Sage X3 is concerned...

**Speaker 2:** Yeah, not related. Not related. That's just our new, it's just our new ERP system uh and we don't, we don't integrate any of that e-commerce stuff with with Sage.

**Speaker 1:** Okay, that's good to know, that's good to know. Um, okay. Now um, how are you aware of how your SQL Server database is secured within your AWS in- environment?

**Speaker 2:** Yeah, it is on uh, it's we have a VPN that goes in uh to to its private network, and uh it's it's not public. So um, you just go in to uh, you VPN in to get to the VPC and then you do a remote desktop or SSH if if you choose Ubuntu to get to the database.

**Speaker 1:** Okay, I see. That um, I was asking because that's going to matter when it comes to, you know, how we access everything via the automation scripts. So that lets me know, you know...

**Speaker 2:** Right. So the automation scripts would be uh, it could do outgoing, but uh no incoming.

**Speaker 1:** Okay. Understood. Um, alright. Do you already have developer accounts for um, these services?

**Speaker 2:** Yes. For both, yes. Yes we do.

**Speaker 1:** Alright, so I'll, I'll be able to get you know, whatever, whatever I needed to.

**Speaker 2:** Yeah, uh yeah that's that's the thing is we have it for uh for our current consultant uh Venkat, and uh after over eight weeks uh I don't even have a POC. Uh of of what of so it, in the last three weeks it's been like Groundhog Day where he's asking the same questions over and over again.

**Speaker 1:** Okay, understood.

**Speaker 2:** So that's why uh the next um consultant that would take over, uh I would like uh probably weekly uh milestones. Uh and then and then I would check and see where uh where you're at uh on on each each week at a time.

**Speaker 1:** Understood. Okay.

**Speaker 2:** Not trying to to do any micromanagement, but I guess I was too laissez-faire with the first one.

**Speaker 1:** No, no, that's that's totally fair. And um, you know, I'm I'm not offended by that at all. Uh I understand you have a business to run. So... I'm just reading through a few more of my notes just to make sure I ask, you know, the right questions before we go any further.

**Speaker 2:** Oh yeah, yeah.

**Speaker 1:** Okay. Um, do you guys ever have um a situation where there's a partial fulfillment on a single line item? So maybe so for example like, a customer orders five units, Amazon fulfills three. Um, and you guys have to fill fulfill the rest?

**Speaker 2:** Uh, never. Because uh if Amazon doesn't have uh all all five, they will reject the line. They would, yeah, reject it. Then I would uh then we would need to fulfill the whole thing.

**Speaker 1:** Oh, okay, I see. And is that the case with any um, any quantity or um I guess number of items, if they don't have the whole thing they'll reject it entirely?

**Speaker 2:** Um, yes, that is correct. Well if now if they they have uh, they don't have the item in their system at all they don't inventory that item at all, then they'll just reject that line and take uh take the other one.

**Speaker 1:** Oh, okay, okay. Got it. Okay, um, I think that's uh... that's all I have right now. Um, so as far as like... I I understand you know you want the the automation for um, you know, get getting the Shopify orders, checking the status, updating the updating the order status, and then you guys have the nightly sync which is you said 5:30 PST so that'll be 8:30 our time. Um, and that all that's that's automated, so we can put that on a cron job, that's no problem. Um, but as far as like visibility, um, what what are you looking for in terms of that in terms of a UI, um, you know, yeah what could you give me a high-level overview of of what you would want for that?

**Speaker 2:** Uh well uh basic the the the first uh phase of it would be just to uh be able to uh when when you uh bring in the uh the orders uh from Shopify and decipher which ones are fulfilled by Amazon, uh to uh to uh allow me to know which which uh line items I need to really ship in a day. And uh and then when the uh tracking numbers are submitted back to the system uh to the same database, then you would look at for any uh line uh orders that have a have a uh a status of let's say five with a tracking number and then you your uh nightly process would update the um the Shopify fulfillment to say that it's complete. Uh the second phase would be to uh to take all the all the orders and list them on a uh on like uh a website that's uh secured where uh where it could only whitelist like from our office. And uh our IT could handle that part. But uh if you could make like a little uh website front that shows the orders that need to be um approved uh for uh for that day, and then when they approve them, then it would automatically set the order to 5 and then we could ship it out right away. That would be the second part of it.

**Speaker 1:** Right, so so just basic basically a a dashboard that's showing you what's being you know, what's being automated, the status of everything. Okay, that makes sense.

**Speaker 2:** Yeah, right, yeah. So so whether it's uh fully uh supported by uh fulfilled by Amazon or or by us, it will just show all the orders. But it it would also have some uh flags from uh Shopify like which ones are uh possibly fraudulent or or the credit card uh billing address doesn't uh the zip code doesn't match the the shipping address or you know, some some some clues that that uh where where uh they could do easy research on on the order if if they need to uh investigate it or not.

**Speaker 1:** Mhm. Okay. Alright, I think I just have potentially one more question and then uh... give me give me a moment to formulate it.

**Speaker 2:** Sure.

**Speaker 1:** You sound like you're made by AI! [laughs]

**Speaker 2:** [laughs] Well I I mean I I really have to like um I I really have to like prepare for um because I I'm uh just the way my brain works like I think about a lot of things at once so I gotta I gotta keep myself structured.

**Speaker 1:** Yeah, right. That's good.

**Speaker 2:** Like it it sounds like you're more IT than uh than uh a developer.

**Speaker 1:** No, I'm I'm definitely a developer. Um, if you if you saw the stuff I've I've developed...

**Speaker 2:** Yeah, yeah. No because as IT we're naturally um um pessimistic. So whatever we do is okay, how would it screw up? That's just a natural feeling.

**Speaker 1:** Oh, I I see, yeah. I gotcha.

**Speaker 2:** Where a developer, they would just take the requirements and do it write the requirements, even if they see a train hitting it. But that's just going by the requirement.

**Speaker 1:** Yeah, well I I think the reason why I approach it that way is because um, you know I'm I'm more so of a a product-minded minded developer and you know I understand that like um, when you're building products of of any sort like there are a lot of edge cases and things that can like derail your entire thing. And I've I've run into that so many times so I'm like I'm I'm kind of anal about...

**Speaker 2:** Yeah, well well we're not selling it, that's so it's an internal uh process so it's that it doesn't have to be bulletproof.

**Speaker 1:** Got it. I mean but you know I still like still I want to do a I want to make sure I do a good job for you guys, so...

**Speaker 2:** Yeah. Yeah. Because if you uh spend too much time making it bulletproof, then it'll delay things. Rather than just just do happy path, let it let us go go through it and then maybe we we would both find out uh some some edge cases that that it doesn't handle. But uh the idea is to get it out there just to see how it goes.

**Speaker 1:** Yeah, absolutely. Um, so actually I I think I'm I think I'm okay from from here uh or from here. So um what I do understand is that that that Amazon um API is it's it's it can be uh pretty cumbersome. So that'll probably be the the most annoying thing, but for for the most part what you seem what you want seems pretty straightforward, it's just a matter of like getting started and getting into your system and...

**Speaker 2:** Right, it's more like um, you download all the the Shopify orders, and then you do like a like a for loop to take the orders and see whatever Amazon is suppor- is fulfilling, and then uh updating the databases, okay, you know, you put a flag "Amazon's taking over this". And you just loop through all the uh all the orders and then once that aren't checked for Amazon, then I know that I need to fulfill.

**Speaker 1:** Mhm. Okay. Alright, um...

**Speaker 2:** So we don't do thousands of orders, so it could be slow. It's not it doesn't have to be high-performing.

**Speaker 1:** Oh, okay. Perfect, then. Well, we'll get you guys going very soon.

**Speaker 2:** I'm trying to I'm trying to think up ahead for you to save yourself some...

**Speaker 1:** Yeah, 'cause I I'm yeah I was thinking about that, I'm like shoot, do we need to implement like lambda functions and scalability and all that? Alright, so this is straightforward, no no worries.

**Speaker 2:** Yeah, yeah, yeah. Same thing like uh, maybe the frequency of of fetching the uh the uh the new orders would be like every 15 minutes or something.

**Speaker 1:** Okay. So how many how many orders would you say you process a day?

**Speaker 2:** Roughly.

**Speaker 1:** Roughly.

**Speaker 2:** Well uh how many orders we ship uh at least ship from our warehouse per day is is is probably like like 25. But uh as far as how how many orders per day, I have to get that to you. I have to let you know.

**Speaker 1:** Okay, no problem. Yeah. Alright, so um... yeah those those are all the questions and concerns that I have at this time. Um, I I guess my only other question to you is like um how how would you like to get started or or how soon would you like to get started?

**Speaker 2:** Oh well um, I have to interview the other candidates, and then and then I have to present it to uh management uh because uh I've already been burned once so I'm not sure uh yeah I have to say "hey, this will really work." So uh um yeah, I'm not in good standing right now but uh yeah, so we'll have to see when once I um select a candidate it may it may require like a second interview uh uh from from management if if they uh think it's a good idea. Um... yeah, but pretty much once we accept, then we would like to go as soon as possible.

**Speaker 1:** Alright, well fair enough. So what I'll do um, just to like I guess help you guys make a faster decision, um and I I'll build a... I'll basically scaffold it out for you and guys and present you guys with a blueprint so you guys can like get some insight into what I plan on building already. And um, I'll I'll get that over to you guys if not tonight, sometime um tomorrow afternoon. And uh would you would you like me to send that directly or send that through Angeline?

**Speaker 2:** Uh, you can uh you can send it to me and cc Thomas or or Ang- I never met her before, so um... whatever whatever you choose.

**Speaker 1:** Let me see. Do I have... I don't I don't think I have your email, could I get that from you?

**Speaker 2:** Yeah, uh kimn K I M N at innova.com.

**Speaker 1:** That's uh two Ns right?

**Speaker 2:** No, K I M N. M as in Mary, N as in Nancy.

**Speaker 1:** No, I was saying Innova, that's two Ns?

**Speaker 2:** Uh yeah, yeah. Two Ns. I N N O V A. Yeah.

**Speaker 1:** Okay. Um, alright. And then you said to CC Thomas?

**Speaker 2:** Yeah. Do you know his uh email?

**Speaker 1:** Uh, let me... he's he's from Robert Half... uh...

**Speaker 2:** Oh yeah, I can't write in chat because you're on the bridge. Um... Thomas, T H O M A S dot Ngo N G O at roberthalf.com.

**Speaker 1:** Okay. Um, alright so I'll get that over to you as soon as possible so just be on the lookout and uh I guess uh I'll hear back from you soon whether you guys make a decision or not.

**Speaker 2:** Oh okay, sure.

**Speaker 1:** Alright, thank you. Thank you.

**Speaker 2:** Okay. Alright. Alright, bye.

**Speaker 1:** Bye.
