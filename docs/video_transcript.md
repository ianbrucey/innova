Here is the verbatim transcript extracted from the video you uploaded:

---

Okay.

We're all good.

Okay.

Oh, you already started it. Cool. Nice.

Right. Uh, did I? Let me see.

Yeah. Yeah. Okay. It's recording. Okay. So, uh, so the first thing that happens...

You're not sharing your screen.

Oh, oh, okay. Sorry. I need to share my screen. Yeah. Yeah. Yeah. Share my screen.

So this recording, uh, just for the recording. So this recording is, uh, um, uh, for us, uh, uh, so that Kim can show us what is, um, what is the manual process that we currently follow to fulfill the orders.

Right. Okay.

So, uh, I get an email, uh, that says, that says that, uh, Shopify orders that, uh, that they're released. This is part of, um, this part will be, like, after the fulfillment is fulfilled, uh, is done, then, uh, then the next step is to, uh, review the order process and have, uh, the, uh, the, the person who, who approves the orders to go through that system to approve. And so then it will just be automatically set on, uh, you know, I don't have to read emails. I could just go to, to the, the site and, and figure out which, which orders to, to release. But, uh, for now we're doing this manually because we don't have a process.

Uh, so this says that all Shopify orders up to, uh, uh, order 16,400 are, uh, are approved, uh, and these two are canceled. Uh, so I just keep note of that. But if it's already canceled, I don't have to worry about that.

Okay.

So then I go to the shop—so 16,400. I go to the Shopify site. Okay. And, uh, the first thing I do is I release anything that says Amazon out of stock. Uh, it pretty much has a tag of "rejected by Amazon". I, if I, if it has a tag of rejected by Amazon, I know every item, uh, in the, uh, in there needs, needs to be shipped by us. So, uh, these are all one items, uh, right here. So, so I would go through and, uh, note that, uh, on, on my database to, to approve.

So I go to my database here. Uh, uh, this, this query, I just—it's a, it's a query that says, "update the order table, uh, set the admin order status equal 5", and 5 is enumerated to, uh, just ready to ship. That's something internal, not by Shopify, that's something that we set internally.

Okay.

And set the, set the update date to today's date. So that would help me know, uh, when we do inventory reconciliation, I know when we shipped this order, would be, uh, would be on today. So let's say that the end of the month was, uh, uh, was, uh, like, um, uh, like over the weekend or something, and I only get anything up to this month and, and everything, uh, after that will be, you know, reconciled next month. Uh, even though the order date would be in March or whatever, if we process it in April, then we know that's for April, uh, April reconciliation.

Okay. So, uh, uh, it goes through the order table, uh, joining the line items and, and only get line items that, that, uh, that equal 1, uh, meaning that it requires shipping. And then, uh, and then the order shipping is, uh, is a table that gets the customer information, and then, uh, and then I just put in the order numbers manually one by one. So, uh, let me put this on another screen, I'll just type them in.

Okay.

So this is the part I want automated. So if we just pull from this, uh, so, uh, 162... 16264, 16265, 162...

It's a weekend, so it, it grabs, you know, pretty much three days worth of, uh, worth of orders, so that's why it's more than, than, uh, normal. So, uh, and this is manual data entry, so there's a chance of, um, uh, data entry error. Uh, but we'll find that out when, uh, when something doesn't ship.

Okay.

Okay. So, so I typed in all the orders that have to do with, uh, that have, that, that we know that Amazon is not fulfilling before.

Okay.

And then I go through this, this Irvine, uh, tab, uh, that, that sorts out anything that, uh,

Um, can you please hold? Sorry Kim, I have a question. Can you please go back to Amazon Out of Stock again?

Uh-huh. Yeah.

Okay. And, um, what, what are the, um, how many, um, numbers did we put in? Did we put in all the ones that are unfulfilled? Or did you just, um...

1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15.

Okay. So all the ones that are unfulfilled in Amazon Out of Stock and rejected by Amazon, we put them here. Okay.

Yeah. Yeah, so 15, right? I count 15, and I highlighted 15.

Okay.

And then, uh, and then the next thing I do is, this is the trickier one. This is, uh, why, you know, uh, the headache that I go through is because some of these items may be, uh, shipped from Amazon. Um, so, so see the things that say partially fulfilled, uh, I, I would need to go through and, uh, find out of these, these fulfilled items—oh well these are fulfilled already, but if there's anything not fulfilled, which ones are Amazon handling?

So, uh, this one's completely fulfilled. So, so that's where, uh, where your, your, uh, your, uh, backend programming would do is download all the, all the line items that we need to ship from Amazon, uh, from Shopify, and then, uh, like, um, do not create fulfillments for items that, uh, that Amazon is already handling. So, uh,

Okay.

So right now, let's say, uh, let's say this, this one is unfulfilled. Okay. So it has these two orders. So then to find out, uh, if, uh, if I need to really ship them, uh, then I need to go to, uh, let me...

Amazon Seller Central. Manage Orders. And then I type in the order. So that was, which order was that? 3846 16384. 16384. Okay, so I know that Amazon is handling both of these.

Okay.

So that, that means I skip this. I do not fulfill this. Um, from your backend, uh, you need to, you need to get those items and say "okay, fulfilled by Amazon" or something like that, so we know that we don't need to, uh, we don't need to fulfill it.

Okay. Can we take a step back, please? Um.

Sure.

Kim. So, uh, please click on Irvine. I got the Amazon Out of Stock part, um, uh, understood. Um, and for, uh, can you please click on, yeah, Irvine. So now, um, do you go through all the orders here? Or what kind of, um, orders are you targeting here?

Okay.

Do you see the unfulfilled part, or do you...

Well, even if it's partially fulfilled, I don't know if it's fully, fully taken or not. Right? So sometimes Amazon would fulfill one item, and then I look, I need to look at the other item. Um, so a workaround I've done because I've done this for a couple weeks now, uh, is I look for anything that has multiple items, uh, and then it adds up to 349, 149. Then, then I know that it's a promotion. But that's something that I, I don't want your programming to look at. This is something that, that I'm just trying to make it quick, get by, and then after a couple days if there's something still not fulfilled, then I need to really look at it.

Okay.

Uh, so let me see if there's... even here, see there's one... there's one item here that has not... okay so I need to fulfill that one too. Okay. So then...

What?

Uh, can you, can you please open that and then um, I want to be able to tell the program on what you are looking at here. So, so you look at this unfulfilled item and then...

Okay. So I see one unfulfilled item. Um, I know that Amazon doesn't fulfill this, but that's because I know they don't fulfill it. But what your programming is supposed to do is, you're supposed to look at that order, go into the Amazon site, find out that it's blank. They don't fulfill this item.

Okay. So, okay, when we put in that particular order number in Amazon, it shouldn't return anything. So that tells us that Amazon does not care about this particular order.

That means we need—that means that, uh, we need to fulfill it from, from...

We need to fulfill this. Okay. So this is one use case. Okay, got it.

Yeah. This is the only use case that you do! Uh, you take the Shopify orders, you look at what Amazon is fulfilling. If Amazon is fulfilling those, then you deduct those items and quantities from what we need to ship from the Irvine warehouse. Um, if, you know, if it doesn't show up, then those items need to be fulfilled by the Irvine warehouse. So it's the use case is the same.

Okay. So what do we do for this on the next step? So we know that Amazon does not...

Oh, so I need to add—so I need to add that order number to, to the list, 16289.

Okay.

16289. Okay, and then manually look at the others. So...

Okay.

So, uh...

And, um, can we go to the partially fulfilled one again, please?

Okay. 16252. Hold on. Uh, let me...

Look at partially fulfilled.

Um, so... So, uh, well, this is already fulfilled. Um...

Okay. Now I want to know—no, can you please go back to that one?

Okay.

So here, um, we know that the fulfilled item is indeed fulfilled. Uh, so I don't need to check up on the fulfilled items, but I need to look at the unfulfilled items and we want to make sure that those are shippable. So how do I know that these three are, uh, how do you know that these three are not shippable? I mean, we understand...

Uh, well there will be a tag. There will be a tag on the line item that says "not shippable," that requires shipping. And, and if it's true, then, then it requires shipping. If it's false, then it doesn't.

Okay. Please bear with me. Let me just, I'm just on my desktop, I'm just looking at some line items. Please bear with me.

Okay.

Line items...

Okay. I do see that. It... there are, um... there is a tag called "requires shipping," which is switching from true or false. So... okay.

Yeah, so that also comes in my script right here. It says "where requires shipping equals 1." Uh, I don't know the exact, uh, value that Shopify would send it, but it's in there.

Yes. I understand now. So... give me one second. Yeah, okay. So there is, okay... now we got more details. So this is good. Yep.

Yeah. So I would keep on going through... So this is 349. This is what you don't do. This is what I'm just doing this in my head. Uh, this is something that you should not apply to your logic. Uh, so this one is... most likely if it has a non-Amazon SKU and there's only one line item, uh, then it's a 100% chance that we need to fulfill that item. So I don't even need to look at it. I mean, I need to put this order in...

Okay, but I need to look at it. My program should look at it.

Yeah, you need to look at it. Your program should look at it at all times. Yeah.

162... 16257...

And that is a rough estimate that that's it. So then I execute this. Uh, so I double check, uh, see how many orders there are. There are 19. And, um, and I see that there's no tracking numbers, so I'm not replicating any orders, uh, for shipping, uh, at least shipping from our warehouse. And then, uh, I execute it. So I do that.

And then I go into, uh... and then I go into my Access database that connects to the database, and it makes a view, um... so... So it pretty much goes to this query that's in the database to find out what orders have not shipped. But that's something that we do on the database side. Um, okay. So my code doesn't have to worry about it. All I need to do is...

No, no, no, I would create that code. Yeah, right. So then I create the all the pick tickets that need to be shipped out, and I print it to the warehouse to process.

And then the rest is automated on their end, uh, because what they do is they scan, uh, they scan the order for them to import the address, and they know what item to pick. And then once they process the FedEx shipping on their side, the tracking number automatically fills this row... it fills this row, it gets updated here.

And then there would be a daily process, let's say at 5:30 Pacific Time, that would look for any order that has the admin order status 5, uh, okay, and has a tracking number. If that's the case, then you update the fulfillment for Shopify, uh, that it was fulfilled. And then when that is complete, then you update this order status to 4, which our enumeration would be, uh, fulfillments complete.

So in here, um, can we take a couple steps back, and then, um... so you update these orders, um, and...

So these orders are automatically done... let's say if I did, um...

See how these tracking numbers are already filled in?

Okay.

So let's look at one, uh, 15112. Uh, we would go to orders here.

Search 15112. And here we go. It shipped in February. And, uh, and it was fulfilled by us, for 167896, and... 17... So this came from our system. And I would expect your process to take these tracking numbers from our local database that's on our AWS cloud, and update the fulfillment on Shopify.

Okay.

So do you want me to verify this also? Or where does my code... where does the automation process stop? So we look at the Irvine view, we look at those views, we get the orders and then...

Uh, you only scan the database. You just scan the database. You don't worry about Irvine or whatever. You don't worry about those. You just look at any order that has admin order status 5 with a tracking number. And you go through the whole batch that's there, and then once you're done with that process, then you finish uploading the fulfillments to Shopify, then you update the admin order status to 4. That's it.

Um... I think I'm missing a few steps here, Kim. Can you please bear with me? Um, so can you please go back to the Shopify again?

Okay.

Okay. Go back to the orders, please.

So we are back at step 1. So, um, we first one we are going to tackle is Amazon out of stock items, and then we go to Irvine. And then in Irvine, um, I go through all the orders that we, you know, we talked about. And figure out what needs to be manually fulfilled. And I grab all the list of the order numbers that need to be manually fulfilled. And then I go to the database. Can you please go to the database, please?

Okay. Yep.

Okay. And then here, I'm going to put that list in, and then update the status to 5. Um, what is it enumerated to? When we say update to status 5, are we saying that it's ready to be shipped?

Yeah, that means that the warehouse would grab... they could ship those orders and whatever line items that need to be fulfilled from our Irvine warehouse.

Okay. So here, I do that, and then my process is done, right? In the morning, yes.

First part of the process is done. Next part, for me to continue the process, fulfillment needs to work on it, like the Access part and all that part, they need to fulfill, they need to create the labels, and then...

Yeah, yeah, that's behind the scenes. You don't... you're not responsible for that.

Yeah, so that is Ops. So after Ops does that, um, then we get the tracking numbers for these particular list of items, I mean these particular list of orders, right? So do we... so I'm trying to figure out, how do I kick that process off? So do I... it's a batch process, fixed time, every day, at 5:30 in the afternoon, Pacific time.

Okay, so 5:30...

You could do 5:30, you could do 6:00, just after end of day. Yeah.

Okay. So after... so I need to wait for end of day, and then, um, my process expects that for these particular list of orders, um, I expect tracking numbers in the database. Is that correct?

You would do a query to look for any order that has an admin order status equal 5 and has a tracking number. Because let's say that I process these orders today, okay? But they don't ship... they don't process them. They're too busy, they have some other priorities. And we get to tomorrow, and we load another bunch of orders. So just don't look for anything today. Just look for anything that has admin order status equal 5 and a tracking number that's not null and not empty string.

Okay.

And then you send those orders and the items that are fulfilled within these tracking numbers to, uh, to update Shopify.

Okay. Can you please show me on screen, that is what I'm getting at. So can you please take an order number and show me on screen, what needs to be, what needs to happen?

I don't do anything! I mean the process in our daily process is it takes the orders and just marks those orders as all outstanding items as fulfilled. Uh, and then once that's done, then we update the status equal 4.

And that's the part that I need you for, because our current process is it completes ANY open line item that hasn't shipped. Make it complete. Uh, I need you to just update just the lines that we need to fulfill.

Okay. I think I got most of it, but I still am not very clear. Please bear with me. What's your use case that you're not sure of?

Okay. So at the end of the day, um, some process runs and tracking numbers get updated here. So I need to look for...

No, tracking numbers are already there. You don't update any tracking numbers. The tracking numbers are already there, and you need to take those tracking numbers to update Shopify.

No, I am not updating it. I am saying somebody else is going to work on the process, and then after they generate the labels, the tracking numbers get updated here. Now I need to run my process, and you are saying I need to look for... I don't look for those particular set of orders. You want me to look for all the orders which are in a particular status. What status am I looking for?

Status 5.

Okay. Because in the morning, you set it at status 5. Okay. When we grabbed... remember this? Up here? We set it to 5, so it's going to stay 5 until you make it 4 after you update the fulfillment up to Shopify.

Okay. But yeah, so I'm just... my process kickstarts at the end of the day, um, and I look for all the orders which are in status 5, and then what is the next step? What do I do with that?

No... it's what YOU do with that! Okay. You need to... you're automating it. You are automating it! Because we have an automation process that I need to update so you automate it. Because our automation, it updates all the orders with all the line items in it. But if it's a split shipment issue, then it'll close the Amazon fulfillment too, and then we'll end up shipping short. Which is bad.

Okay.

Okay. So I kickstart the process, I get a list of orders, so what is the next step? So what do I do?

Okay. So when do you want to run this batch process? You know, what time at the end of each day?

Um, after your process is complete...

No, no, don't worry about my process. You're going to run a fixed process, at a fixed time at the end of the day. What time?

Um, I'm thinking probably 8:00 PM.

8:00 PM? Okay. 8:00 PM Pacific time. Okay. 8:00 PM Pacific time, you have a scheduled task to go through all the orders that have an admin order status equal to 5, AND a tracking number that's not null and not empty.

Okay.

And then you send those orders and the items that are fulfilled within these tracking numbers to, uh, to update Shopify.

Okay.

And then after you're done with that, then you update that batch of orders that you submitted and update to 4, so we don't need to ship it again.

Okay.

Okay.

Understood. So there ends the process, right? We don't do anything else after that.

Yeah, yeah, process has ended. Yes.

Awesome. Thank you so much, Kim. This was really helpful. But I'll need to write it down, condense everything we discussed Thursday and today, and this is very helpful. Um, and I need to get, yeah, also a few more details, but I think most of my questions are answered. I will come back to you if I have more. Do you happen to have a workflow document or anything that...

No, I'm going to create that based on all, you know, what we have, because in my head it was just like 3 parts that we wanted to do, but we got multiple use cases now. I will write down everything so everybody can understand and we can figure out where we are at and then we are on the same page.

Yeah. Yeah, I mean the other option is you could do brute force and just look at all the orders that are not completely fulfilled and just compare it with Amazon, you could do that too. You don't have to specifically look at... I mean it's a smaller subset, but you don't have to abide by that.

True. But the thing is, um, we are going to be making unnecessary API calls that way, and then we also have rate limit restrictions, so that's going to restrict our program, so it's going to slow us down.

Okay. So that's my only concern, but I'll look at the use cases.

Well because we do want to download all the orders anyway. We do want to download all the orders into our database anyway.

Yeah. Oh, okay. Okay.

Yeah, so you could run a batch process like every 15 minutes or something to just grab all the orders that are in Shopify. Yeah.

That makes sense. We'll do, Kim. So the next thing I'm going to do is do all of that, create a document flow and then show it to you, get your feedback and try to add more based on your feedback.

Yeah, okay.

Awesome. Thank you so much, Kim.

Okay. All right. All right, bye.

Bye.

---

Would you like me to map this transcript to specific timestamps, or is there another part of the workflow you'd like me to break down next?
